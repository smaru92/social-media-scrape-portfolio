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
        Schema::table('tiktok_videos', function (Blueprint $table) {
            $table->integer('share_count')->default(0)->after('comment_count')->comment('공유 수');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_videos', function (Blueprint $table) {
            $table->dropColumn('share_count');
        });
    }
};