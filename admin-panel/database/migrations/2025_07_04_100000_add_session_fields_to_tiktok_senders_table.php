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
        Schema::table('tiktok_senders', function (Blueprint $table) {
            $table->string('session_file_path')->nullable()->after('login_password')->comment('틱톡 세션 파일 경로');
            $table->timestamp('session_updated_at')->nullable()->after('session_file_path')->comment('세션 갱신 시간');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_senders', function (Blueprint $table) {
            $table->dropColumn(['session_file_path', 'session_updated_at']);
        });
    }
};
