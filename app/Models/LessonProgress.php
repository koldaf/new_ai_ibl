<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'lesson_id',
        'engage_completed',
        'explore_completed',
        'explain_completed',
        'elaborate_completed',
        'evaluate_completed',
        'completed',
        'completed_at',
    ];

    protected $casts = [
        'engage_completed' => 'boolean',
        'explore_completed' => 'boolean',
        'explain_completed' => 'boolean',
        'elaborate_completed' => 'boolean',
        'evaluate_completed' => 'boolean',
        'completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}