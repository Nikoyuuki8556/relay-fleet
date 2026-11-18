<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 【設計書からの拡張】機体ごとにON/OFF切替できる整備インターバル。
// 累積飛行時間(分)が maintenance_interval_minutes を超えたら整備予定(aircraft_schedules)を自動生成する。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aircraft', function (Blueprint $table) {
            $table->boolean('maintenance_interval_enabled')->default(false)->after('image_url');
            $table->unsignedInteger('maintenance_interval_minutes')->nullable()->after('maintenance_interval_enabled');
            $table->unsignedInteger('accumulated_minutes_since_maintenance')->default(0)->after('maintenance_interval_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('aircraft', function (Blueprint $table) {
            $table->dropColumn(['maintenance_interval_enabled', 'maintenance_interval_minutes', 'accumulated_minutes_since_maintenance']);
        });
    }
};
