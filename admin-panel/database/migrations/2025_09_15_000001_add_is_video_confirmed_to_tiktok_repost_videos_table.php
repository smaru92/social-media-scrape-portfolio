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
        Schema::table('tiktok_repost_videos', function (Blueprint $table) {
            $table->enum('is_checked', ['Y', 'N'])->default('N')->after('video_url')->comment('영상 확인여부');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_repost_videos', function (Blueprint $table) {
            $table->dropColumn('is_checked');
        });
    }
};