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
        Schema::create('tiktok_message_logs', function (Blueprint $table) {
            $table->id();

            $table->integer('tiktok_user_id')->nullable()->comment('틱톡사용자 id');
            $table->integer('tiktok_message_id')->nullable()->comment('틱톡 메시지 id');
            $table->integer('tiktok_sender_id')->nullable()->comment('틱톡 발신자 id');
            $table->string('result')->nullable()->comment('전송결과');
            $table->string('result_text')->nullable()->comment('전송결과 메시지');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_message_logs');
    }
};
