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
            $table->enum('review_status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->comment('심사 상태: pending(대기), approved(승인), rejected(탈락)');
            $table->integer('review_score')->nullable()->comment('심사 점수');
            $table->text('review_comment')->nullable()->comment('심사 코멘트');
            $table->timestamp('reviewed_at')->nullable()->comment('심사 일시');
            $table->unsignedBigInteger('reviewed_by')->nullable()->comment('심사자 ID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_users', function (Blueprint $table) {
            $table->dropColumn([
                'review_status',
                'review_score',
                'review_comment',
                'reviewed_at',
                'reviewed_by'
            ]);
        });
    }
};
