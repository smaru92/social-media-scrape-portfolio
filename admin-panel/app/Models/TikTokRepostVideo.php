<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TikTokRepostVideo extends Model
{
    use HasFactory;

    protected $table = 'tiktok_repost_videos';
    
    protected $guarded = [];

    protected $casts = [
        'posted_at' => 'datetime',
        'view_count' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
        'share_count' => 'integer',
        'is_checked' => 'string',
    ];

    public function brandAccount()
    {
        return $this->belongsTo(TikTokBrandAccount::class, 'tiktok_brand_account_id');
    }
}