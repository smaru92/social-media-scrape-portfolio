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
            // 새로운 필드 추가
            $table->string('postal_code')->nullable()->after('address')->comment('우편번호');
            $table->string('category1')->nullable()->after('country')->comment('분류1');
            $table->string('category2')->nullable()->after('category1')->comment('분류2');
            $table->text('brand_feedback')->nullable()->after('category2')->comment('브랜드 피드백');
            $table->enum('repost_permission', ['Y', 'N', 'P'])->default('P')->after('brand_feedback')->comment('리포스트 허가');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_user_personal_infos', function (Blueprint $table) {
            $table->dropColumn([
                'postal_code',
                'category1',
                'category2',
                'brand_feedback',
                'repost_permission',
                'form_submitted_at'
            ]);
        });
    }
};
