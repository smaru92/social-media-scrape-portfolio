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
        Schema::create('tiktok_upload_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiktok_user_id')->constrained('tiktok_users')->onDelete('cascade');
            $table->text('request_content')->comment('요청사항');
            $table->timestamp('requested_at')->comment('요청일시');
            $table->date('deadline_date')->nullable()->comment('게시 기한');
            $table->boolean('is_uploaded')->default(false)->comment('업로드 여부');
            $table->string('upload_url')->nullable()->comment('업로드 URL');
            $table->string('upload_thumbnail_url')->nullable()->comment('업로드 썸네일 URL');
            $table->timestamp('uploaded_at')->nullable()->comment('업로드 일시');
            $table->foreignId('tiktok_video_id')->nullable()->constrained('tiktok_videos')->onDelete('set null');
            $table->timestamps();

            $table->index('tiktok_user_id');
            $table->index('is_uploaded');
            $table->index('requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_upload_requests');
    }
};