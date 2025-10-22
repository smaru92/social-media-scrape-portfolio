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
        Schema::table('tiktok_upload_requests', function (Blueprint $table) {
            $table->text('request_tags')->nullable()->after('request_content')->comment('해시태그 및 멘션 (공백 구분)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_upload_requests', function (Blueprint $table) {
            $table->dropColumn('request_tags');
        });
    }
};