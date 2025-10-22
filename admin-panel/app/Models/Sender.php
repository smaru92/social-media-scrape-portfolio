<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sender extends Model
{
    use HasFactory;

    protected $table = 'senders';
    public $timestamps = true;
    protected $guarded = [];

    public function message_logs(): HasMany
    {
        return $this->hasMany(MessageLog::class, 'sender_id', 'id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id', 'id');
    }
}
