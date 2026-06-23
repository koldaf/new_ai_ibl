<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonStageContent;
use App\Models\LessonMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExploreActivityCompletionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function explore_stage_cannot_be_completed_until_all_activities_are_checked(): void
    {
        $studentData = [
            'name' => 'Student One',
            'password' => bcrypt('password'),
            'role' => 'student',
        ];

        $studentData['email'] = 'student-one@example.test';

        $student = User::query()->forceCreate($studentData);
        $lesson = Lesson::create([
            'title' => 'Energy Transfer',
            'description' => 'Lesson description',
        ]);

        $content = LessonStageContent::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'content_type' => 'wysiwyg',
            'content' => '<p>Explore this simulation.</p>',
        ]);

        $media = LessonMedia::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'media_type' => 'pdf',
            'file_path' => 'lessons/test/activity.pdf',
            'file_name' => 'activity.pdf',
            'title' => 'Activity sheet',
            'order' => 1,
        ]);

        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
        ]);

        $response = $this->actingAs($student)->postJson(route('student.lessons.stages.complete', [
            'lesson' => $lesson,
            'stage' => 'explore',
        ]));

        $response
            ->assertStatus(422)
            ->assertJson([
                'error' => 'Complete every Explore activity before marking this stage as complete.',
            ]);

        $this->actingAs($student)->postJson(route('student.lessons.stages.activities.complete', [
            'lesson' => $lesson,
            'stage' => 'explore',
        ]), [
            'activity_type' => 'stage_content',
            'activity_reference_id' => $content->id,
            'completed' => true,
        ])->assertOk();

        $this->actingAs($student)->postJson(route('student.lessons.stages.activities.complete', [
            'lesson' => $lesson,
            'stage' => 'explore',
        ]), [
            'activity_type' => 'media',
            'activity_reference_id' => $media->id,
            'completed' => true,
        ])->assertOk();

        $completionResponse = $this->actingAs($student)->postJson(route('student.lessons.stages.complete', [
            'lesson' => $lesson,
            'stage' => 'explore',
        ]));

        $completionResponse
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(
            LessonProgress::query()
                ->where('user_id', $student->id)
                ->where('lesson_id', $lesson->id)
                ->value('explore_completed')
        );
    }
}