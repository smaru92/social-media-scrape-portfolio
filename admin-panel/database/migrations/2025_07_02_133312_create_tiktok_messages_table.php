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
        Schema::create('tiktok_messages', function (Blueprint $table) {
            $table->id();
            $table->integer('tiktok_sender_id')->nullable()->comment('틱톡 sender_id');
            $table->string('title')->nullable()->comment('제목');
            $table->text('message_json')->nullable()->comment('메시지 내용 json');
            $table->boolean('is_complete')->nullable()->comment('전송완료여부');
            $table->timestamp('start_at')->nullable()->comment('메시지 전송시작시간');
            $table->timestamp('end_at')->nullable()->comment('메시지 전송시작시간');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_messages');
    }
};
