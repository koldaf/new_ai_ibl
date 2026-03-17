<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EngageMcqAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'lesson_id',
        'engage_mcq_question_id',
        'selected_option',
        'is_correct',
        'resolved_feedback',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function question()
    {
        return $this->belongsTo(EngageMcqQuestion::class, 'engage_mcq_question_id');
    }
}