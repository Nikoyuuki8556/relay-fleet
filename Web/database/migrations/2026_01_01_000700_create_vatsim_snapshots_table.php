<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vatsim_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('callsign', 16);
            $table->unsignedInteger('cid');
            $table->char('departure', 4)->nullable();
            $table->char('arrival', 4)->nullable();
            // NOT NULLのままデフォルト値を指定しないと一部のMySQL/MariaDB設定で
            // "Invalid default value" エラーになるためnullableにしている（アプリ側では必ず値をセットする）。
            $table->timestamp('logon_time')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('raw_json')->nullable();

            $table->index(['cid', 'logon_time'], 'idx_cid_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vatsim_snapshots');
    }
};
