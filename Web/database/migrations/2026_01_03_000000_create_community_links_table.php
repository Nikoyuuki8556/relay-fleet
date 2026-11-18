<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 【設計書からの拡張】コミュニティの関連リンク（Discordサーバー、公式サイト等）。
// 元の3章DDLには無い機能追加のため 2026_01_03 の日付を振っている。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities');
            $table->string('label', 50);
            $table->string('url', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['community_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_links');
    }
};
