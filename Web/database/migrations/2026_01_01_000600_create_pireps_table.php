<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pireps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aircraft_id')->constrained('aircraft');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('reservation_id')->constrained('aircraft_reservations');
            $table->char('departure_icao', 4);
            $table->char('arrival_icao', 4);
            $table->string('route')->nullable();
            // NOT NULLのままデフォルト値を指定しないと一部のMySQL/MariaDB設定で
            // "Invalid default value" エラーになるためnullableにしている（アプリ側では必ず値をセットする）。
            $table->timestamp('departure_time')->nullable();
            $table->timestamp('arrival_time')->nullable();
            $table->unsignedInteger('flight_time_min');
            $table->enum('network', ['vatsim', 'offline'])->default('offline');
            $table->string('vatsim_callsign', 16)->nullable();
            $table->enum('verification_status', ['pending', 'auto_verified', 'verified', 'rejected'])->default('pending');
            $table->string('volanta_flight_ref', 64)->nullable(); // 将来Volanta連携用の予約カラム
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pireps');
    }
};
