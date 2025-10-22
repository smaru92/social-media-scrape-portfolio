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
        // 사용할 문자 템플릿 (사용할지말지 아직 미정)
        Schema::create('tiktok_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable()->comment('제목');
            $table->text('message_header_json')->nullable()->comment('메시지 상단 메시지목록 json');
            $table->text('message_body_json')->nullable()->comment('메시지 내용 메시지목록 json');
            $table->text('message_footer_json')->nullable()->comment('메시지 하단 메시지목록 json');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_message_templates');
    }
};
