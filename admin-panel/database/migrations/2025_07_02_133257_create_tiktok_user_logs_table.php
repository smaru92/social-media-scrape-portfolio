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
        // 틱톡 사용자 수집 로그
        Schema::create('tiktok_user_logs', function (Blueprint $table) {
            $table->id();
            $table->string('keyword')->default('')->nullable()->comment('검색시 사용한 키워드');
            $table->integer('min_followers')->default(0)->nullable()->comment('검색시 최소 팔로워 수 조건');
            $table->integer('search_user_count')->default(0)->nullable()->comment('탐지한 유저 수');
            $table->integer('save_user_count')->default(0)->nullable()->comment('저장한 유저 수');
            $table->boolean('is_error')->default(false)->nullable()->comment('에러발생 여부');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_user_logs');
    }
};
