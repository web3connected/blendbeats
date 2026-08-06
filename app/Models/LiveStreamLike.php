<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveStreamLike extends Model
{
    protected $fillable = ['live_stream_id', 'user_id'];
}
