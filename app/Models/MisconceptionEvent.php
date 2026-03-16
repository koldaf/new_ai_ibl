<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MisconceptionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'lesson_id',
        'stage',
        'misconception_id',
        'source',
        'student_answer',
        'evidence_span',
        'confidence',
        'status',
    ];

    protected $casts = [
        'confidence' => 'float',
    ];

    public function misconception()
    {
        return $this->belongsTo(LessonMisconception::class, 'misconception_id');
    }
}
