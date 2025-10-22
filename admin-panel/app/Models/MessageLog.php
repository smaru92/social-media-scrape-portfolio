<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MessageLog extends Model
{
    use HasFactory;

    protected $table = 'message_logs';

    public $timestamps = false;

    protected $guarded = [];

    // Define the message relationship
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id', 'id');
    }

    // Define the sender relationship
    public function sender(): BelongsTo
    {
        return $this->belongsTo(Sender::class, 'sender_id', 'id');
    }

    // Define the seller relationship
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'id');
    }
}
