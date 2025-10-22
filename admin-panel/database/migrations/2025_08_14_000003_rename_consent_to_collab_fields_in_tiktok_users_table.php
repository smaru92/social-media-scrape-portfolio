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
            $table->renameColumn('has_consented', 'is_collaborator');
            $table->renameColumn('consented_at', 'collaborated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_users', function (Blueprint $table) {
            $table->renameColumn('is_collaborator', 'has_consented');
            $table->renameColumn('collaborated_at', 'consented_at');
        });
    }
};