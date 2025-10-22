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
        Schema::create('tiktok_auto_dm_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('설정 이름');
            $table->boolean('is_active')->default(false)->comment('활성화 여부');
            $table->unsignedBigInteger('tiktok_sender_id')->nullable()->comment('발신 계정 ID');
            $table->unsignedBigInteger('tiktok_message_template_id')->nullable()->comment('메시지 템플릿 ID');
            $table->enum('schedule_type', ['daily', 'weekly', 'monthly'])->default('daily')->comment('발송 주기');
            $table->time('schedule_time')->default('09:00:00')->comment('발송 시간');
            $table->integer('schedule_day')->nullable()->comment('발송 요일(주간: 0-6) 또는 일(월간: 1-31)');
            $table->integer('min_review_score')->default(0)->comment('최소 심사 점수');
            $table->timestamp('last_sent_at')->nullable()->comment('마지막 발송 일시');
            $table->timestamps();

            $table->foreign('tiktok_sender_id')
                ->references('id')
                ->on('tiktok_senders')
                ->onDelete('set null');

            $table->foreign('tiktok_message_template_id')
                ->references('id')
                ->on('tiktok_message_templates')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_auto_dm_configs');
    }
};
