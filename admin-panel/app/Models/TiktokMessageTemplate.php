<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiktokMessageTemplate extends Model
{
    use HasFactory;
    protected $table = 'tiktok_message_templates';
    public $timestamps = true;
    protected $guarded = [];
    
    protected $casts = [
        'message_header_json' => 'array',
        'message_body_json' => 'array',
        'message_footer_json' => 'array',
    ];
}
