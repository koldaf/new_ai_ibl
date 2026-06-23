<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonStageContent extends Model
{
    use HasFactory;

    protected $table = 'lesson_stage_contents';

    protected $fillable = ['lesson_id', 'stage', 'content_type', 'activity_mode', 'content'];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}