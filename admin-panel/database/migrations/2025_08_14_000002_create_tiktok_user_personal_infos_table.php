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
        Schema::create('tiktok_user_personal_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiktok_user_id')->constrained('tiktok_users')->onDelete('cascade');
            $table->string('name')->nullable()->comment('이름');
            $table->string('email')->nullable()->comment('이메일');
            $table->string('phone')->nullable()->comment('전화번호');
            $table->date('birth_date')->nullable()->comment('생년월일');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->comment('성별');
            $table->string('address')->nullable()->comment('주소');
            $table->string('city')->nullable()->comment('도시');
            $table->string('state')->nullable()->comment('시/도');
            $table->string('postal_code')->nullable()->comment('우편번호');
            $table->string('country')->nullable()->comment('국가');
            $table->text('additional_info')->nullable()->comment('추가 정보');
            $table->timestamps();

            $table->index('tiktok_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_user_personal_infos');
    }
};