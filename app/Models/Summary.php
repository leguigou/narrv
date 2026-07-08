<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Summary extends Model
{
    protected $fillable = [
        'transcript_id', 'content', 'model',
        'temperature', 'tone', 'length', 'language',
    ];

    protected $casts = [
        'temperature' => 'float',
    ];

    public function transcript()
    {
        return $this->belongsTo(Transcript::class);
    }
}
