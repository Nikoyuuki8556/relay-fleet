<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 【設計書からの拡張】機体が所属する航空会社（エアライン）。コミュニティごとに複数登録できる。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('icao_code', 3)->nullable();
            $table->string('iata_code', 2)->nullable();
            $table->string('logo_url')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['community_id', 'name'], 'uq_airline_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airlines');
    }
};
