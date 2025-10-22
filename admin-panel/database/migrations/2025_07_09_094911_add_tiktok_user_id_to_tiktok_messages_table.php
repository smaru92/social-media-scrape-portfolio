<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tiktok_messages', function (Blueprint $table) {
            $table->foreignId('tiktok_user_id')->nullable()->constrained('tiktok_users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_messages', function (Blueprint $table) {
            $table->dropForeign(['tiktok_user_id']);
            $table->dropColumn('tiktok_user_id');
        });
    }
};
