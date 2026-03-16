<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonMisconception extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'stage',
        'concept_tag',
        'label',
        'description',
        'correct_concept',
        'remediation_hint',
        'source',
        'status',
        'confidence',
        'created_by',
    ];

    protected $casts = [
        'confidence' => 'float',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
