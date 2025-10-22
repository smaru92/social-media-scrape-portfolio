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
        Schema::create('tiktok_repost_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiktok_brand_account_id')->constrained('tiktok_brand_accounts')->onDelete('cascade')->comment('브랜드 계정 ID');
            $table->string('video_url')->comment('동영상 주소');
            $table->string('title')->comment('제목');
            $table->string('thumbnail_url')->nullable()->comment('썸네일 주소');
            $table->bigInteger('view_count')->default(0)->comment('조회수');
            $table->timestamp('posted_at')->nullable()->comment('게시일');
            $table->bigInteger('like_count')->default(0)->comment('좋아요수');
            $table->bigInteger('comment_count')->default(0)->comment('댓글 수');
            $table->integer('share_count')->default(0)->comment('공유 수');
            $table->timestamps();

            $table->index('tiktok_brand_account_id');
            $table->index('posted_at');
            $table->index('view_count');
            $table->index('like_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_repost_videos');
    }
};