<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = [
        'youtube_id', 'url', 'title', 'channel_name', 'channel_url',
        'duration', 'thumbnail_url', 'language', 'status',
        'error_message', 'formats_json',
    ];

    protected $casts = [
        'duration' => 'integer',
        'formats_json' => 'array',
    ];

    public function transcript()
    {
        return $this->hasOne(Transcript::class);
    }
}
