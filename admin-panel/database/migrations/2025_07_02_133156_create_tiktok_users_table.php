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
        // 틱톡 사용자 테이블
        Schema::create('tiktok_users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable()->comment('계정명');
            $table->string('keyword')->nullable()->comment('검색시 사용한 키워드');
            $table->string('nickname')->nullable()->comment('사용자 닉네임, 간단소개');
            $table->integer('followers')->nullable()->comment('팔로워 수');
            $table->string('profile_url')->nullable()->comment('주소');
            $table->text('bio')->nullable()->comment('자기소개');
            $table->string('memo')->nullable()->comment('비고');
            $table->timestamps();

            // 소프트 딜리트용 컬럼 추가
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_users');
    }
};
