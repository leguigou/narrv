<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $appends = [
        'youtube_url',
    ];

    protected $fillable = [
        'youtube_id', 'url', 'title', 'channel_name', 'channel_url',
        'duration', 'published_at', 'thumbnail_url', 'language', 'is_visible', 'status',
        'error_message', 'formats_json', 'chapters_json', 'chapter_thumbnails_status',
    ];

    protected $casts = [
        'duration' => 'integer',
        'published_at' => 'datetime',
        'formats_json' => 'array',
        'chapters_json' => 'array',
        'is_visible' => 'boolean',
    ];

    public function transcript()
    {
        return $this->hasOne(Transcript::class);
    }

    public function getYoutubeUrlAttribute(): string
    {
        return "https://www.youtube.com/watch?v={$this->youtube_id}";
    }
}
