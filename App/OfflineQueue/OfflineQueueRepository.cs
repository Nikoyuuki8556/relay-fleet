using Microsoft.Data.Sqlite;

namespace RelayFleetTracker.OfflineQueue;

/// <summary>
/// 7.5節「オフラインキュー（SQLite）」の実装。
/// 送信失敗（ネットワーク断・5xx）時はここにINSERTし、バックグラウンドタイマーで再送する。
/// </summary>
public sealed class OfflineQueueRepository
{
    private readonly string _connectionString;

    public OfflineQueueRepository(string dbPath)
    {
        _connectionString = $"Data Source={dbPath}";
        Initialize();
    }

    private void Initialize()
    {
        using var conn = new SqliteConnection(_connectionString);
        conn.Open();
        using var cmd = conn.CreateCommand();
        cmd.CommandText = """
            CREATE TABLE IF NOT EXISTS pending_pireps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payload_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                retry_count INTEGER NOT NULL DEFAULT 0
            );
            """;
        cmd.ExecuteNonQuery();
    }

    public long Enqueue(string payloadJson)
    {
        // Microsoft.Data.Sqliteには(System.Data.SQLiteと違い) Connection.LastInsertRowIdが無いため、
        // 同一コネクション上でSELECT last_insert_rowid()を続けて呼ぶ(SQLite側がコネクション単位で保持する値)。
        using var conn = new SqliteConnection(_connectionString);
        conn.Open();
        using (var cmd = conn.CreateCommand())
        {
            cmd.CommandText = """
                INSERT INTO pending_pireps (payload_json, created_at, retry_count)
                VALUES ($payload, $createdAt, 0);
                """;
            cmd.Parameters.AddWithValue("$payload", payloadJson);
            cmd.Parameters.AddWithValue("$createdAt", DateTimeOffset.UtcNow.ToString("o"));
            cmd.ExecuteNonQuery();
        }

        using var idCmd = conn.CreateCommand();
        idCmd.CommandText = "SELECT last_insert_rowid();";

        return (long)idCmd.ExecuteScalar()!;
    }

    public List<PendingPirep> GetAll()
    {
        using var conn = new SqliteConnection(_connectionString);
        conn.Open();
        using var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT id, payload_json, created_at, retry_count FROM pending_pireps ORDER BY id;";

        var list = new List<PendingPirep>();
        using var reader = cmd.ExecuteReader();
        while (reader.Read())
        {
            list.Add(new PendingPirep
            {
                Id = reader.GetInt64(0),
                PayloadJson = reader.GetString(1),
                CreatedAt = DateTimeOffset.Parse(reader.GetString(2)),
                RetryCount = reader.GetInt32(3),
            });
        }

        return list;
    }

    public void IncrementRetry(long id)
    {
        using var conn = new SqliteConnection(_connectionString);
        conn.Open();
        using var cmd = conn.CreateCommand();
        cmd.CommandText = "UPDATE pending_pireps SET retry_count = retry_count + 1 WHERE id = $id;";
        cmd.Parameters.AddWithValue("$id", id);
        cmd.ExecuteNonQuery();
    }

    public void Remove(long id)
    {
        using var conn = new SqliteConnection(_connectionString);
        conn.Open();
        using var cmd = conn.CreateCommand();
        cmd.CommandText = "DELETE FROM pending_pireps WHERE id = $id;";
        cmd.Parameters.AddWithValue("$id", id);
        cmd.ExecuteNonQuery();
    }
}
