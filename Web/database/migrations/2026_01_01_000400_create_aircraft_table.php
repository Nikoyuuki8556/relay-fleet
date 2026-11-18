<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aircraft', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities');
            $table->string('registration', 10);
            $table->char('icao_type', 4);
            $table->string('livery_name', 100)->nullable();
            $table->enum('sim_platform', ['ANY', 'MSFS2020', 'MSFS2024', 'XP12'])->default('ANY');
            $table->char('home_base_icao', 4);
            $table->char('current_location_icao', 4);
            $table->enum('status', ['available', 'reserved', 'in_flight', 'maintenance', 'retired'])->default('available');
            $table->unsignedInteger('version')->default(0); // 楽観的ロック用
            $table->string('image_url')->nullable();
            $table->timestamps();

            $table->unique(['community_id', 'registration'], 'uq_registration');
            $table->index('current_location_icao', 'idx_location');
            $table->index('status', 'idx_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aircraft');
    }
};
