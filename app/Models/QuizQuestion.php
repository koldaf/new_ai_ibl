<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuizQuestion extends Model
{
    //
    use HasFactory;

    protected $table = 'lesson_quiz_questions';

    protected $fillable = [
        'lesson_id',
        'question',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_option'
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
