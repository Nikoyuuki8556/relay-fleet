<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aircraft', function (Blueprint $table) {
            $table->foreignId('airline_id')->nullable()->after('community_id')->constrained('airlines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aircraft', function (Blueprint $table) {
            $table->dropConstrainedForeignId('airline_id');
        });
    }
};
