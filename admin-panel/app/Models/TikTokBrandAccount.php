<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TikTokBrandAccount extends Model
{
    use HasFactory;

    protected $table = 'tiktok_brand_accounts';
    
    protected $guarded = [];

    protected $casts = [
        'last_scraped_at' => 'datetime',
        'is_verified' => 'boolean',
        'repost_accounts' => 'array',
        'followers' => 'integer',
        'following_count' => 'integer',
        'video_count' => 'integer',
    ];

    // 상태 상수
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    public static function getStatusLabels()
    {
        return [
            self::STATUS_ACTIVE => '활성',
            self::STATUS_INACTIVE => '비활성',
        ];
    }

    public function repostVideos()
    {
        return $this->hasMany(TikTokRepostVideo::class, 'tiktok_brand_account_id');
    }
}