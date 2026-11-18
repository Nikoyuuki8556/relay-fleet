using System.Runtime.InteropServices;

namespace RelayFleetTracker.Auth;

/// <summary>
/// 7.4節 f: トークンをWindows資格情報マネージャー(Credential Manager)に暗号化保存する。
/// advapi32.dll の CredWrite/CredRead/CredDelete をP/Invokeする、資格情報マネージャーの正攻法実装。
/// （DPAPI直書きファイルより資格情報マネージャーの方がWindows標準UIから確認・削除でき運用しやすいため採用）
/// </summary>
public static class CredentialStore
{
    private const string TargetName = "RelayFleetTracker:SanctumToken";
    private const int CredTypeGeneric = 1;
    private const int CredPersistLocalMachine = 2;

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct CREDENTIAL
    {
        public int Flags;
        public int Type;
        public string TargetName;
        public string Comment;
        public long LastWritten;
        public int CredentialBlobSize;
        public IntPtr CredentialBlob;
        public int Persist;
        public int AttributeCount;
        public IntPtr Attributes;
        public string TargetAlias;
        public string UserName;
    }

    [DllImport("advapi32.dll", SetLastError = true, CharSet = CharSet.Unicode)]
    private static extern bool CredWrite([In] ref CREDENTIAL credential, uint flags);

    [DllImport("advapi32.dll", SetLastError = true, CharSet = CharSet.Unicode)]
    private static extern bool CredRead(string target, int type, int reservedFlag, out IntPtr credentialPtr);

    [DllImport("advapi32.dll", SetLastError = true, CharSet = CharSet.Unicode)]
    private static extern bool CredDelete(string target, int type, int flags);

    [DllImport("advapi32.dll")]
    private static extern void CredFree(IntPtr cred);

    public static void SaveToken(string token)
    {
        var blob = System.Text.Encoding.Unicode.GetBytes(token);
        var blobPtr = Marshal.AllocHGlobal(blob.Length);
        try
        {
            Marshal.Copy(blob, 0, blobPtr, blob.Length);

            var cred = new CREDENTIAL
            {
                Type = CredTypeGeneric,
                TargetName = TargetName,
                CredentialBlobSize = blob.Length,
                CredentialBlob = blobPtr,
                Persist = CredPersistLocalMachine,
                UserName = "RelayFleetTracker",
                Comment = "RelayFleet Sanctum personal access token",
            };

            if (!CredWrite(ref cred, 0))
            {
                throw new InvalidOperationException($"資格情報の保存に失敗しました (Win32Error={Marshal.GetLastWin32Error()})");
            }
        }
        finally
        {
            Marshal.FreeHGlobal(blobPtr);
        }
    }

    public static string? TryLoadToken()
    {
        if (!CredRead(TargetName, CredTypeGeneric, 0, out var credPtr))
        {
            return null;
        }

        try
        {
            var cred = Marshal.PtrToStructure<CREDENTIAL>(credPtr);
            if (cred.CredentialBlob == IntPtr.Zero || cred.CredentialBlobSize == 0) return null;

            var bytes = new byte[cred.CredentialBlobSize];
            Marshal.Copy(cred.CredentialBlob, bytes, 0, cred.CredentialBlobSize);

            return System.Text.Encoding.Unicode.GetString(bytes);
        }
        finally
        {
            CredFree(credPtr);
        }
    }

    public static void DeleteToken() => CredDelete(TargetName, CredTypeGeneric, 0);
}
