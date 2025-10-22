<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class TiktokSender extends Model
{
    use HasFactory;
    protected $table = 'tiktok_senders';
    public $timestamps = true;
    protected $guarded = [];

    public function tiktok_message_logs(): HasMany
    {
        return $this->hasMany(TikTokMessageLog::class, 'tiktok_sender_id', 'id');
    }

    public function tiktok_messages(): HasMany
    {
        return $this->hasMany(TikTokMessage::class, 'tiktok_sender_id', 'id');
    }
}

