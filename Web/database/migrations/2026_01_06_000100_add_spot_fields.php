<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 【設計書からの拡張】到着スポット番号。
// pireps.arrival_spot: PIREP提出時に入力する「到着した駐機スポット」の自己申告値。
// aircraft.current_spot: 直近のPIREPのarrival_spotで更新される、機体の現在の駐機スポット（キャッシュ）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pireps', function (Blueprint $table) {
            $table->string('arrival_spot', 20)->nullable()->after('arrival_icao');
        });

        Schema::table('aircraft', function (Blueprint $table) {
            $table->string('current_spot', 20)->nullable()->after('current_location_icao');
        });
    }

    public function down(): void
    {
        Schema::table('pireps', function (Blueprint $table) {
            $table->dropColumn('arrival_spot');
        });
        Schema::table('aircraft', function (Blueprint $table) {
            $table->dropColumn('current_spot');
        });
    }
};
