<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokAutoDmConfig extends Model
{
    protected $table = 'tiktok_auto_dm_configs';

    protected $fillable = [
        'name',
        'country',
        'is_active',
        'tiktok_sender_id',
        'tiktok_message_template_id',
        'schedule_type',
        'schedule_time',
        'schedule_day',
        'min_review_score',
        'last_sent_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'schedule_time' => 'datetime:H:i:s',
        'last_sent_at' => 'datetime',
    ];

    // 스케줄 타입 상수
    const SCHEDULE_DAILY = 'daily';
    const SCHEDULE_WEEKLY = 'weekly';
    const SCHEDULE_MONTHLY = 'monthly';

    public static function getScheduleTypeLabels(): array
    {
        return [
            self::SCHEDULE_DAILY => '매일',
            self::SCHEDULE_WEEKLY => '매주',
            self::SCHEDULE_MONTHLY => '매월',
        ];
    }

    public static function getCountryOptions(): array
    {
        return [
            'KR' => '🇰🇷 한국 (KR)',
            'US' => '🇺🇸 미국 (US)',
            'JP' => '🇯🇵 일본 (JP)',
            'CN' => '🇨🇳 중국 (CN)',
            'TW' => '🇹🇼 대만 (TW)',
            'VN' => '🇻🇳 베트남 (VN)',
            'TH' => '🇹🇭 태국 (TH)',
            'ID' => '🇮🇩 인도네시아 (ID)',
            'PH' => '🇵🇭 필리핀 (PH)',
            'MY' => '🇲🇾 말레이시아 (MY)',
            'SG' => '🇸🇬 싱가포르 (SG)',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(TiktokSender::class, 'tiktok_sender_id');
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(TiktokMessageTemplate::class, 'tiktok_message_template_id');
    }
}
