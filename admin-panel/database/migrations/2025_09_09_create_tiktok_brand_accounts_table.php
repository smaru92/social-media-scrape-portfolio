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
        Schema::create('tiktok_brand_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique()->comment('브랜드 틱톡 계정명');
            $table->string('brand_name')->comment('브랜드명');
            $table->string('country', 2)->nullable()->comment('국적 (ISO 3166-1 alpha-2)');
            $table->string('category')->nullable()->comment('브랜드 카테고리');
            $table->string('nickname')->nullable()->comment('표시 이름');
            $table->integer('followers')->default(0)->comment('팔로워 수');
            $table->integer('following_count')->default(0)->comment('팔로잉 수');
            $table->integer('video_count')->default(0)->comment('비디오 수');
            $table->text('profile_url')->nullable()->comment('프로필 URL');
            $table->text('profile_image')->nullable()->comment('프로필 이미지 URL');
            $table->text('bio')->nullable()->comment('계정 소개');
            $table->boolean('is_verified')->default(false)->comment('공식 인증 여부');
            $table->timestamp('last_scraped_at')->nullable()->comment('마지막 스크랩 시간');
            $table->json('repost_accounts')->nullable()->comment('리포스트 계정 목록');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('계정 상태');
            $table->string('memo')->nullable()->comment('비고');
            $table->timestamps();
            
            // 인덱스
            $table->index('country');
            $table->index('category');
            $table->index('status');
            $table->index('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_brand_accounts');
    }
};