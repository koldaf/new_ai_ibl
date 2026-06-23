<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeacherDashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function teacher_dashboard_only_lists_owned_lessons(): void
    {
        $teacher = User::query()->forceCreate($this->userPayload('Teacher One', 'teacher', 'teacher-one@example.test'));
        $otherTeacher = User::query()->forceCreate($this->userPayload('Teacher Two', 'teacher', 'teacher-two@example.test'));
        $student = User::query()->forceCreate($this->userPayload('Student One', 'student', 'student-one@example.test'));

        $ownedLesson = Lesson::create([
            'title' => 'Forces and Motion',
            'description' => 'Forces lesson',
            'teacher_id' => $teacher->id,
        ]);

        $otherLesson = Lesson::create([
            'title' => 'Chemical Reactions',
            'description' => 'Chemistry lesson',
            'teacher_id' => $otherTeacher->id,
        ]);

        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $ownedLesson->id,
            'explore_completed' => true,
        ]);

        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $otherLesson->id,
            'explore_completed' => true,
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee('Forces and Motion');
        $response->assertDontSee('Chemical Reactions');
    }

    #[Test]
    public function teacher_cannot_open_report_for_unowned_lesson(): void
    {
        $teacher = User::query()->forceCreate($this->userPayload('Teacher One', 'teacher', 'teacher-one@example.test'));
        $otherTeacher = User::query()->forceCreate($this->userPayload('Teacher Two', 'teacher', 'teacher-two@example.test'));
        $lesson = Lesson::create([
            'title' => 'Cells',
            'description' => 'Biology lesson',
            'teacher_id' => $otherTeacher->id,
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.lessons.show', $lesson));

        $response->assertStatus(403);
    }

    private function userPayload(string $name, string $role, string $email): array
    {
        $payload = [
            'name' => $name,
            'password' => bcrypt('password'),
            'role' => $role,
        ];

        $payload['email'] = $email;

        return $payload;
    }
}