<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $fillable = [
        'transcript_id', 'target_language', 'content', 'model',
    ];

    public function transcript()
    {
        return $this->belongsTo(Transcript::class);
    }
}
