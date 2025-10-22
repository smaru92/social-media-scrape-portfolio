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
        Schema::table('tiktok_users', function (Blueprint $table) {
            $table->string('status', 50)->default('unconfirmed')->before('is_collaborator')->comment('진행상태: unconfirmed(미확인), dm_sent(DM전송완료), dm_replied(DM답변완료), form_submitted(구글폼제출완료), upload_waiting(영상업로드대기), upload_completed(영상업로드완료)');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
