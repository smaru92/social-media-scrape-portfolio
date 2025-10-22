<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiktokUploadRequest extends Model
{
    use HasFactory;

    protected $table = 'tiktok_upload_requests';
    
    protected $guarded = [];

    protected $casts = [
        'requested_at' => 'datetime',
        'deadline_date' => 'date',
        'uploaded_at' => 'datetime',
        'is_uploaded' => 'boolean',
        'is_confirm' => 'boolean',
    ];

    public function getRequestTagsArrayAttribute()
    {
        return $this->request_tags ? explode(' ', $this->request_tags) : [];
    }

    public function setRequestTagsArrayAttribute($value)
    {
        $this->attributes['request_tags'] = is_array($value) ? implode(' ', $value) : $value;
    }

    public function tiktokUser()
    {
        return $this->belongsTo(TiktokUser::class, 'tiktok_user_id');
    }

    public function tiktokVideo()
    {
        return $this->belongsTo(TiktokVideo::class, 'tiktok_video_id');
    }
}