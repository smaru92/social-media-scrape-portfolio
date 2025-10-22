<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TiktokSender;
use App\Models\TiktokUser;

class TiktokMessage extends Model
{
    use HasFactory;

    protected $table = 'tiktok_messages';
    public $timestamps = true;
    protected $guarded = [];

    protected $casts = [
        'is_auto' => 'boolean',
        'is_complete' => 'boolean',
        'success_count' => 'integer',
        'fail_count' => 'integer',
    ];

    public function tiktok_message_logs(): HasMany
    {
        return $this->hasMany(TikTokMessageLog::class, 'tiktok_message_id', 'id');
    }

    public function tiktok_sender(): BelongsTo
    {
        return $this->belongsTo(TikTokSender::class, 'tiktok_sender_id', 'id');
    }

    public function tiktok_message_template(): BelongsTo
    {
        return $this->belongsTo(TiktokMessageTemplate::class, 'tiktok_message_template_id', 'id');
    }

    public function tiktok_users(): BelongsToMany
    {
        return $this->belongsToMany(TikTokUser::class, 'tiktok_message_logs', 'tiktok_message_id', 'tiktok_user_id');
    }
}
