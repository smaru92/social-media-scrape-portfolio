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
        Schema::table('tiktok_message_logs', function (Blueprint $table) {
            $table->string('message_text')->default('')->after('tiktok_message_id')->comment('보낸 메시지 내용');
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_message_logs', function (Blueprint $table) {
            //
        });
    }
};
