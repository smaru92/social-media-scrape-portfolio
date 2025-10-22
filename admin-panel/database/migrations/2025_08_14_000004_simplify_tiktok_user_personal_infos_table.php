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
        Schema::table('tiktok_user_personal_infos', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'gender', 'city', 'state', 'postal_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_user_personal_infos', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->comment('생년월일');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->comment('성별');
            $table->string('city')->nullable()->comment('도시');
            $table->string('state')->nullable()->comment('시/도');
            $table->string('postal_code')->nullable()->comment('우편번호');
        });
    }
};