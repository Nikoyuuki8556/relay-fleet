<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aircraft_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aircraft_id')->constrained('aircraft');
            $table->foreignId('reserved_by_user_id')->constrained('users');
            $table->timestamp('reserved_at')->useCurrent();
            // NOT NULLのままデフォルト値を指定しないと、一部のMySQL/MariaDB設定で
            // "Invalid default value" エラーになるためnullableにしている（アプリ側では必ず値をセットする）。
            $table->timestamp('expires_at')->nullable(); // reserved_at + 12時間がデフォルト
            $table->timestamp('released_at')->nullable();

            $table->index(['aircraft_id', 'released_at'], 'idx_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aircraft_reservations');
    }
};
