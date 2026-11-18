<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 【設計書からの拡張】機体が使用中のときの順番待ちキュー。
// 予約が解放されたタイミングでstatus='waiting'の先頭を自動的に予約へ昇格させる。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aircraft_reservation_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aircraft_id')->constrained('aircraft')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('requested_at')->useCurrent();
            $table->enum('status', ['waiting', 'claimed', 'cancelled'])->default('waiting');
            $table->timestamp('claimed_at')->nullable();

            $table->index(['aircraft_id', 'status', 'requested_at'], 'idx_queue_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aircraft_reservation_queue');
    }
};
