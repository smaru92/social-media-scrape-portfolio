<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $table = 'messages';
    public $timestamps = true;
    protected $guarded = [];

    public function message_logs(): HasMany
    {
        return $this->hasMany(MessageLog::class, 'message_id', 'id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Sender::class, 'sender_id', 'id');
    }


    public function sellers(): BelongsToMany
    {
        return $this->belongsToMany(Seller::class, 'message_logs', 'message_id', 'seller_id');
    }
}
