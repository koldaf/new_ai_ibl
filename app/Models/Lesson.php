<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'teacher_id', 'subject', 'grade_level', 'description', 'vector_store_path', 'processing_status', 'lesson_material_file'];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function stageContents()
    {
        return $this->hasMany(LessonStageContent::class);
    }

    public function media()
    {
        return $this->hasMany(LessonMedia::class);
    }

    public function activityCompletions()
    {
        return $this->hasMany(LessonActivityCompletion::class);
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

    public function getStageActivities(string $stage): Collection
    {
        $activities = collect();
        $content = $this->getStageContent($stage);

        if ($content && filled($content->content)) {
            $activities->push([
                'activity_key' => 'stage_content-' . $content->id,
                'activity_type' => 'stage_content',
                'activity_reference_id' => $content->id,
                'title' => ucfirst($stage) . ' lesson content',
                'description' => 'Read and review the lesson content for this stage.',
            ]);
        }

        foreach ($this->getStageMedia($stage) as $media) {
            $activities->push([
                'activity_key' => 'media-' . $media->id,
                'activity_type' => 'media',
                'activity_reference_id' => $media->id,
                'title' => $media->title ?: $media->file_name,
                'description' => $media->description,
                'media_id' => $media->id,
                'media_type' => $media->media_type,
            ]);
        }

        return $activities->values();
    }

    public function countStageActivities(string $stage): int
    {
        return $this->getStageActivities($stage)->count();
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

    public function misconceptions()
    {
        return $this->hasMany(LessonMisconception::class);
    }

    public function engageMcqQuestions()
    {
        return $this->hasMany(EngageMcqQuestion::class);
    }

    public function getEngageMcqQuestion(string $stage = 'engage')
    {
        return $this->engageMcqQuestions()->where('stage', $stage)->first();
    }

    public function engageMcqAttempts()
    {
        return $this->hasMany(EngageMcqAttempt::class);
    }

    public function assignedTeachers()
    {
        return $this->belongsToMany(User::class, 'lesson_teacher', 'lesson_id', 'teacher_id')->withTimestamps();
    }
}