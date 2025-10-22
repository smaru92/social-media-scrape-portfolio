<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiktokUserPersonalInfo extends Model
{
    use HasFactory;

    protected $table = 'tiktok_user_personal_infos';
    
    protected $guarded = [];

    protected $casts = [
        'form_submitted_at' => 'datetime',
    ];


    public function tiktokUser()
    {
        return $this->belongsTo(TiktokUser::class, 'tiktok_user_id');
    }

    public function videos()
    {
        return $this->hasMany(TiktokVideo::class, 'tiktok_user_id', 'tiktok_user_id');
    }

    public function uploadRequests()
    {
        return $this->hasMany(TiktokUploadRequest::class, 'tiktok_user_id', 'tiktok_user_id');
    }
}