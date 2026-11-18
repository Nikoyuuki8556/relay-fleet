<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 設計書 3章 DDL準拠。
// 注意: グローバルなroleカラムは持たない。役割は community_memberships.role に属する。
// 注意: パスワード認証は行わない（Discord/VATSIM OAuthのみ）。password/password_reset_tokensは作らない。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('discord_id', 32)->nullable()->unique();
            $table->string('discord_username', 64)->nullable();
            $table->string('discord_avatar_url')->nullable();
            $table->unsignedInteger('vatsim_cid')->nullable()->unique();
            $table->string('vatsim_rating', 16)->nullable();
            $table->string('vatsim_full_name', 128)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // Blade側のブラウザセッション用（APIはSanctumトークン運用）。
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
