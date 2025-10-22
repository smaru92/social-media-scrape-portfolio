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
        Schema::create('tiktok_senders', function (Blueprint $table) {
            $table->id();
            $table->string('nickname')->nullable()->comment('별칭');
            $table->string('name')->nullable()->comment('계정이름');
            $table->string('login_id')->nullable()->comment('로그인 아이디');
            $table->string('login_password')->nullable()->comment('로그인 패스워드');
            $table->integer('sort')->nullable()->comment('정렬기준');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_senders');
    }
};
