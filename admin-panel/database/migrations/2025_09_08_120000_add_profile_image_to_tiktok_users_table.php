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
            $table->text('profile_image')->nullable()->after('nickname')->comment('프로필 이미지 URL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_users', function (Blueprint $table) {
            $table->dropColumn('profile_image');
        });
    }
};
