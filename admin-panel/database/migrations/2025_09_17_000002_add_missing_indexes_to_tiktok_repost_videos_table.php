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
            if (!$this->indexExists('tiktok_repost_videos', 'tiktok_repost_videos_repost_username_index')) {
                $table->index('repost_username', 'tiktok_repost_videos_repost_username_index');
            }

            if (!$this->indexExists('tiktok_repost_videos', 'tiktok_repost_videos_original_username_index')) {
                $table->index('original_username', 'tiktok_repost_videos_original_username_index');
            }

            if (!$this->indexExists('tiktok_repost_videos', 'tiktok_repost_videos_status_index')) {
                $table->index('status', 'tiktok_repost_videos_status_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_repost_videos', function (Blueprint $table) {
            if ($this->indexExists('tiktok_repost_videos', 'tiktok_repost_videos_repost_username_index')) {
                $table->dropIndex('tiktok_repost_videos_repost_username_index');
            }

            if ($this->indexExists('tiktok_repost_videos', 'tiktok_repost_videos_original_username_index')) {
                $table->dropIndex('tiktok_repost_videos_original_username_index');
            }

            if ($this->indexExists('tiktok_repost_videos', 'tiktok_repost_videos_status_index')) {
                $table->dropIndex('tiktok_repost_videos_status_index');
            }
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists($table, $index): bool
    {
        $indexes = collect(Schema::getConnection()->select("SHOW INDEX FROM {$table}"))
            ->pluck('Key_name')
            ->toArray();

        return in_array($index, $indexes);
    }
};