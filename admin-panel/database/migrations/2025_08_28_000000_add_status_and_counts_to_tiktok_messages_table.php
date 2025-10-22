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
            $table->enum('send_status', ['pending', 'sending', 'completed'])
                ->default('pending')
                ->after('is_complete')
                ->comment('전송 상태: pending(미전송), sending(전송중), completed(전송완료)');
            
            $table->integer('success_count')
                ->default(0)
                ->after('send_status')
                ->comment('전송 성공 인원수');
            
            $table->integer('fail_count')
                ->default(0)
                ->after('success_count')
                ->comment('전송 실패 인원수');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_messages', function (Blueprint $table) {
            $table->dropColumn(['send_status', 'success_count', 'fail_count']);
        });
    }
};