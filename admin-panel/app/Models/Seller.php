<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Tags\HasTags;

class Seller extends Model
{
    use HasFactory;
    use HasTags;

    protected $table = 'sellers';
    public $timestamps = true;
    protected $guarded = [];

//    protected $casts = [
//        'tags' => 'array',
//    ];

    public function message_logs(): HasMany
    {
        return $this->hasMany(MessageLog::class, 'seller_id', 'id');
    }

    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(Message::class, 'message_logs', 'seller_id', 'message_id');
    }
}
