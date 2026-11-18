<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 【設計書からの拡張】機体運用予定。
// - type='event'      : グループフライト等、先々の利用予定をカレンダー的に共有する（誰でも登録可）
// - type='maintenance' : 整備等で機体が使えない期間の周知（dispatcher以上のみ登録可、Web側で制御）
// 3.1節の aircraft_reservations（リアルタイムの排他ロック）とは別物。
// この予定に基づいてaircraft.statusを自動で書き換える処理は本バージョンでは行わない
// （実施する場合は競合判定などの設計が別途必要になるため、意図的にスコープ外にしている）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aircraft_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aircraft_id')->constrained('aircraft');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->enum('type', ['event', 'maintenance'])->default('event');
            $table->string('title', 100);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['aircraft_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aircraft_schedules');
    }
};
