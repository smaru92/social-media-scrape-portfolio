<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokMessageLog extends Model
{
    use HasFactory;
    protected $table = 'tiktok_message_logs';
    public $timestamps = true;
    protected $guarded = [];

    public function tiktok_message(): BelongsTo
    {
        return $this->belongsTo(TiktokMessage::class, 'tiktok_message_id', 'id');
    }

    public function tiktok_sender(): BelongsTo
    {
        return $this->belongsTo(TiktokSender::class, 'tiktok_sender_id', 'id');
    }

    public function tiktok_user(): BelongsTo
    {
        return $this->belongsTo(TiktokUser::class, 'tiktok_user_id', 'id');
    }
}
