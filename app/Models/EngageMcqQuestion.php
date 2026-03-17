<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EngageMcqQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'stage',
        'question',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_option',
        'feedback_option_a',
        'feedback_option_b',
        'feedback_option_c',
        'feedback_option_d',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function attempts()
    {
        return $this->hasMany(EngageMcqAttempt::class);
    }

    public function feedbackForOption(string $option): ?string
    {
        return $this->{'feedback_option_' . strtolower($option)} ?? null;
    }
}