<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 注意: owner_user_id カラムは持たない。オーナーは community_memberships.role='owner' で表現する。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->string('icon_url')->nullable();
            $table->string('discord_guild_id', 32)->nullable();
            $table->enum('visibility', ['public', 'private'])->default('private');
            $table->enum('join_policy', ['open', 'request', 'invite_only'])->default('invite_only');
            $table->boolean('is_recruiting')->default(true);
            $table->string('recruiting_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communities');
    }
};
