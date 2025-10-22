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
            $table->boolean('is_confirm')->default(false)->after('is_uploaded');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_upload_requests', function (Blueprint $table) {
            $table->dropColumn('is_confirm');
        });
    }
};