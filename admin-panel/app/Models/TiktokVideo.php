<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiktokVideo extends Model
{
    use HasFactory;

    protected $table = 'tiktok_videos';
    
    protected $guarded = [];

    protected $casts = [
        'posted_at' => 'datetime',
        'view_count' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
        'share_count' => 'integer',
    ];

    public function tiktokUser()
    {
        return $this->belongsTo(TiktokUser::class, 'tiktok_user_id');
    }
}