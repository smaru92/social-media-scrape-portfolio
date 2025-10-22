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
            $table->boolean('has_consented')->default(false)->after('username')->comment('개인정보 수집 동의 여부');
            $table->timestamp('consented_at')->nullable()->after('has_consented')->comment('동의 일시');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_users', function (Blueprint $table) {
            $table->dropColumn(['has_consented', 'consented_at']);
        });
    }
};
