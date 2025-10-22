<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiktokUser extends Model
{
    use HasFactory;
    protected $table = 'tiktok_users';
    public $timestamps = true;
    protected $guarded = [];

    protected $casts = [
        'is_collaborator' => 'boolean',
        'collaborated_at' => 'datetime',
        'profile_image' => 'string',
        'country' => 'string',
        'reviewed_at' => 'datetime',
    ];

    // 상태 상수 정의
    const STATUS_UNCONFIRMED = 'unconfirmed';
    const STATUS_DM_SENT = 'dm_sent';
    const STATUS_DM_REPLIED = 'dm_replied';
    const STATUS_FORM_SUBMITTED = 'form_submitted';
    const STATUS_UPLOAD_WAITING = 'upload_waiting';
    const STATUS_UPLOAD_COMPLETED = 'upload_completed';

    // 심사 상태 상수
    const REVIEW_STATUS_PENDING = 'pending';
    const REVIEW_STATUS_APPROVED = 'approved';
    const REVIEW_STATUS_REJECTED = 'rejected';

    // 상태 라벨
    public static function getStatusLabels()
    {
        return [
            self::STATUS_UNCONFIRMED => '미확인',
            self::STATUS_DM_SENT => 'DM전송완료',
            self::STATUS_DM_REPLIED => 'DM답변완료',
            self::STATUS_FORM_SUBMITTED => '구글폼제출완료',
            self::STATUS_UPLOAD_WAITING => '영상업로드대기',
            self::STATUS_UPLOAD_COMPLETED => '영상업로드완료',
        ];
    }

    // 상태별 색상
    public static function getStatusColors()
    {
        return [
            self::STATUS_UNCONFIRMED => 'gray',
            self::STATUS_DM_SENT => 'info',
            self::STATUS_DM_REPLIED => 'warning',
            self::STATUS_FORM_SUBMITTED => 'primary',
            self::STATUS_UPLOAD_WAITING => 'danger',
            self::STATUS_UPLOAD_COMPLETED => 'success',
        ];
    }

    // 심사 상태 라벨
    public static function getReviewStatusLabels()
    {
        return [
            self::REVIEW_STATUS_PENDING => '대기',
            self::REVIEW_STATUS_APPROVED => '승인',
            self::REVIEW_STATUS_REJECTED => '탈락',
        ];
    }

    // 심사 상태별 색상
    public static function getReviewStatusColors()
    {
        return [
            self::REVIEW_STATUS_PENDING => 'warning',
            self::REVIEW_STATUS_APPROVED => 'success',
            self::REVIEW_STATUS_REJECTED => 'danger',
        ];
    }

    public function tiktok_message_logs(): HasMany
    {
        return $this->hasMany(MessageLog::class, 'tiktok_user_id', 'id');
    }

    public function tiktok_messages(): BelongsToMany
    {
        return $this->belongsToMany(Message::class, 'message_logs', 'tiktok_user_id', 'tiktok_message_id');
    }

    public function personalInfo()
    {
        return $this->hasOne(TiktokUserPersonalInfo::class, 'tiktok_user_id');
    }

    public function videos()
    {
        return $this->hasMany(TiktokVideo::class, 'tiktok_user_id');
    }

    public function uploadRequests()
    {
        return $this->hasMany(TiktokUploadRequest::class, 'tiktok_user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }
}
