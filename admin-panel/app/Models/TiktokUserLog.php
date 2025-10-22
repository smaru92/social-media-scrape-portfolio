<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiktokUserLog extends Model
{
    use HasFactory;
    protected $table = 'tiktok_user_logs';
    public $timestamps = true;
    protected $guarded = [];
}
