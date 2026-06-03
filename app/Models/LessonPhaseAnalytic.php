<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonPhaseAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'lesson_id',
        'stage',
        'started_at',
        'last_active_at',
        'completed_at',
        'time_spent_seconds',
        'questions_generated',
        'evidence_sources_consulted',
        'reflection_text',
        'reflection_quality_auto',
        'reflection_quality_teacher',
        'reflection_quality_final',
        'evaluation_final_score',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_active_at' => 'datetime',
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

    public function resolveFinalQuality(): ?int
    {
        if (! is_null($this->reflection_quality_teacher)) {
            return (int) $this->reflection_quality_teacher;
        }

        if (! is_null($this->reflection_quality_auto)) {
            return (int) $this->reflection_quality_auto;
        }

        return null;
    }
}
