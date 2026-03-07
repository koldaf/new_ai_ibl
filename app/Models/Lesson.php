<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'subject', 'grade_level', 'description', 'vector_store_path', 'processing_status', 'lesson_material_file'];

    public function stageContents()
    {
        return $this->hasMany(LessonStageContent::class);
    }

    public function media()
    {
        return $this->hasMany(LessonMedia::class);
    }

    /**
     * Get content for a specific stage.
     */
    public function getStageContent($stage)
    {
        return $this->stageContents()->where('stage', $stage)->first();
    }

    /**
     * Get media for a specific stage.
     */
    public function getStageMedia($stage)
    {
        return $this->media()->where('stage', $stage)->orderBy('order')->get();
    }

    // In app/Models/Lesson.php, add:
    public function quizQuestions()
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function progress()
    {
        return $this->hasMany(LessonProgress::class);
    }
}