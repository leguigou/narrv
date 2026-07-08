<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transcript extends Model
{
    protected $fillable = [
        'video_id', 'raw_file_path', 'full_text', 'language',
        'word_count', 'segments_json',
    ];

    protected $casts = [
        'segments_json' => 'array',
        'word_count' => 'integer',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function summaries()
    {
        return $this->hasMany(Summary::class);
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function translations()
    {
        return $this->hasMany(Translation::class);
    }
}
