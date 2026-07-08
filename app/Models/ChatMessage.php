<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = [
        'transcript_id', 'role', 'content', 'tokens_used',
    ];

    protected $casts = [
        'tokens_used' => 'integer',
    ];

    public function transcript()
    {
        return $this->belongsTo(Transcript::class);
    }
}
