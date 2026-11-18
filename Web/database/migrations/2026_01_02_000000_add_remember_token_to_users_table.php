<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 【実運用で発覚した不足分】Auth::login($user, remember: true)（ログイン状態保持機能）は
// Illuminate\Foundation\Auth\User(Authenticatable) がremember_tokenカラムの存在を前提にしているため追加する。
// パスワード認証を使わない設計だったため3章DDL時点では見落としていた。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
