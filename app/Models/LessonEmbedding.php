<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'source_type',
        'source_id',
        'chunk_text',
        'embedding',
        'chunk_index',
    ];

    protected $casts = [
        'embedding' => 'array', // or maybe string, but Laravel can cast array to JSON if needed. For vector, it's a string representation from DB? We'll keep as is.
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}