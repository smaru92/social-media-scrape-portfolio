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
            if (!Schema::hasColumn('tiktok_repost_videos', 'repost_username')) {
                $table->string('repost_username')->after('share_count')->comment('리포스트한 사용자 계정명');
            }

            if (!Schema::hasColumn('tiktok_repost_videos', 'original_video_id')) {
                $table->string('original_video_id')->nullable()->after('repost_username')->comment('원본 비디오 ID');
            }

            if (!Schema::hasColumn('tiktok_repost_videos', 'original_username')) {
                $table->string('original_username')->nullable()->after('original_video_id')->comment('원본 비디오 계정명');
            }

            if (!Schema::hasColumn('tiktok_repost_videos', 'hashtags')) {
                $table->json('hashtags')->nullable()->after('original_username')->comment('해시태그 목록');
            }

            if (!Schema::hasColumn('tiktok_repost_videos', 'scraped_at')) {
                $table->timestamp('scraped_at')->nullable()->after('hashtags')->comment('스크랩 시간');
            }

            if (!Schema::hasColumn('tiktok_repost_videos', 'status')) {
                $table->enum('status', ['active', 'deleted', 'private'])->default('active')->after('scraped_at')->comment('비디오 상태');
            }

            $table->unique(['tiktok_brand_account_id', 'video_url'], 'tiktok_repost_videos_tiktok_brand_account_id_video_url_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_repost_videos', function (Blueprint $table) {
            $table->dropUnique('tiktok_repost_videos_tiktok_brand_account_id_video_url_unique');

            if (Schema::hasColumn('tiktok_repost_videos', 'repost_username')) {
                $table->dropColumn('repost_username');
            }

            if (Schema::hasColumn('tiktok_repost_videos', 'original_video_id')) {
                $table->dropColumn('original_video_id');
            }

            if (Schema::hasColumn('tiktok_repost_videos', 'original_username')) {
                $table->dropColumn('original_username');
            }

            if (Schema::hasColumn('tiktok_repost_videos', 'hashtags')) {
                $table->dropColumn('hashtags');
            }

            if (Schema::hasColumn('tiktok_repost_videos', 'scraped_at')) {
                $table->dropColumn('scraped_at');
            }

            if (Schema::hasColumn('tiktok_repost_videos', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};