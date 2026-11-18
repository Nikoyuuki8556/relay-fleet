<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('role', ['owner', 'admin', 'dispatcher', 'pilot'])->default('pilot');
            $table->enum('status', ['active', 'pending', 'banned'])->default('active');
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['community_id', 'user_id'], 'uq_membership');
            $table->index(['user_id', 'status'], 'idx_user_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_memberships');
    }
};
