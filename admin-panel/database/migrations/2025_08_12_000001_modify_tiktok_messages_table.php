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
        Schema::table('tiktok_messages', function (Blueprint $table) {
            // message_json 컬럼 삭제
            $table->dropColumn('message_json');
            
            // tiktok_message_template_id 컬럼 추가
            $table->unsignedBigInteger('tiktok_message_template_id')->nullable()->after('tiktok_sender_id');
            $table->foreign('tiktok_message_template_id')->references('id')->on('tiktok_message_templates')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_messages', function (Blueprint $table) {
            // tiktok_message_template_id 컬럼 삭제
            $table->dropForeign(['tiktok_message_template_id']);
            $table->dropColumn('tiktok_message_template_id');
            
            // message_json 컬럼 복구
            $table->json('message_json')->nullable()->after('title');
        });
    }
};