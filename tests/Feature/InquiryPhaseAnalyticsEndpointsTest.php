<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonPhaseAnalytic;
use App\Models\LessonProgress;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InquiryPhaseAnalyticsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function student_can_save_reflection_for_a_stage(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Student One', 'student', 'student1@example.test'));
        $lesson = Lesson::create([
            'title' => 'Energy Transfer',
            'description' => 'Lesson description',
        ]);

        $response = $this->actingAs($student)->postJson(route('student.lessons.stages.reflection.save', [
            'lesson' => $lesson,
            'stage' => 'explore',
        ]), [
            'reflection_text' => 'I learned that energy changes form because heat can move between objects. The evidence from our class activity showed temperature differences over time, and next time I will compare my prediction against results before concluding.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage', 'explore');

        $this->assertDatabaseHas('lesson_phase_analytics', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
        ]);

        $analytic = LessonPhaseAnalytic::query()
            ->where('user_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->where('stage', 'explore')
            ->first();

        $this->assertNotNull($analytic);
        $this->assertNotNull($analytic->reflection_quality_auto);
        $this->assertNotNull($analytic->reflection_quality_final);
    }

    #[Test]
    public function teacher_cannot_use_student_reflection_save_endpoint(): void
    {
        $teacher = User::query()->forceCreate($this->userPayload('Teacher One', 'teacher', 'teacher1@example.test'));
        $lesson = Lesson::create([
            'title' => 'Energy Transfer',
            'description' => 'Lesson description',
            'teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAs($teacher)->postJson(route('student.lessons.stages.reflection.save', [
            'lesson' => $lesson,
            'stage' => 'engage',
        ]), [
            'reflection_text' => 'This should fail because the teacher role is not allowed in student routes.',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function student_cannot_use_teacher_override_endpoint(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Student One', 'student', 'student2@example.test'));
        $lesson = Lesson::create([
            'title' => 'Energy Transfer',
            'description' => 'Lesson description',
        ]);

        $response = $this->actingAs($student)->postJson(route('teacher.lessons.student-reflection-score.update', [
            'lesson' => $lesson,
            'student' => $student,
            'stage' => 'engage',
        ]), [
            'reflection_quality_teacher' => 88,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function teacher_cannot_override_reflection_score_for_unowned_lesson(): void
    {
        $teacher = User::query()->forceCreate($this->userPayload('Teacher One', 'teacher', 'teacher2@example.test'));
        $otherTeacher = User::query()->forceCreate($this->userPayload('Teacher Two', 'teacher', 'teacher3@example.test'));
        $student = User::query()->forceCreate($this->userPayload('Student One', 'student', 'student3@example.test'));

        $lesson = Lesson::create([
            'title' => 'Ecosystems',
            'description' => 'Lesson description',
            'teacher_id' => $otherTeacher->id,
        ]);

        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
        ]);

        $response = $this->actingAs($teacher)->postJson(route('teacher.lessons.student-reflection-score.update', [
            'lesson' => $lesson,
            'student' => $student,
            'stage' => 'explain',
        ]), [
            'reflection_quality_teacher' => 90,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function teacher_cannot_override_reflection_score_for_untracked_student(): void
    {
        $teacher = User::query()->forceCreate($this->userPayload('Teacher One', 'teacher', 'teacher4@example.test'));
        $student = User::query()->forceCreate($this->userPayload('Student One', 'student', 'student4@example.test'));

        $lesson = Lesson::create([
            'title' => 'States of Matter',
            'description' => 'Lesson description',
            'teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAs($teacher)->postJson(route('teacher.lessons.student-reflection-score.update', [
            'lesson' => $lesson,
            'student' => $student,
            'stage' => 'elaborate',
        ]), [
            'reflection_quality_teacher' => 74,
        ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function lesson_owner_teacher_can_override_reflection_score_for_tracked_student(): void
    {
        $teacher = User::query()->forceCreate($this->userPayload('Teacher One', 'teacher', 'teacher5@example.test'));
        $student = User::query()->forceCreate($this->userPayload('Student One', 'student', 'student5@example.test'));

        $lesson = Lesson::create([
            'title' => 'Forces',
            'description' => 'Lesson description',
            'teacher_id' => $teacher->id,
        ]);

        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'engage_completed' => true,
        ]);

        LessonPhaseAnalytic::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'engage',
            'reflection_text' => 'I reflected on the difference between mass and weight using examples from class.',
            'reflection_quality_auto' => 63,
            'reflection_quality_final' => 63,
        ]);

        $response = $this->actingAs($teacher)->postJson(route('teacher.lessons.student-reflection-score.update', [
            'lesson' => $lesson,
            'student' => $student,
            'stage' => 'engage',
        ]), [
            'reflection_quality_teacher' => 91,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('reflection_quality_teacher', 91)
            ->assertJsonPath('reflection_quality_final', 91);

        $this->assertDatabaseHas('lesson_phase_analytics', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'engage',
            'reflection_quality_teacher' => 91,
            'reflection_quality_final' => 91,
        ]);
    }

    #[Test]
    public function quiz_submission_persists_evaluation_final_score_in_phase_analytics(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Student One', 'student', 'student6@example.test'));
        $lesson = Lesson::create([
            'title' => 'Electric Circuits',
            'description' => 'Lesson description',
        ]);

        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'engage_completed' => true,
            'explore_completed' => true,
            'explain_completed' => true,
            'elaborate_completed' => true,
            'evaluate_completed' => false,
            'completed' => false,
        ]);

        $q1 = QuizQuestion::create([
            'lesson_id' => $lesson->id,
            'question' => 'Question 1',
            'option_a' => 'A1',
            'option_b' => 'B1',
            'option_c' => 'C1',
            'option_d' => 'D1',
            'correct_option' => 'a',
        ]);

        $q2 = QuizQuestion::create([
            'lesson_id' => $lesson->id,
            'question' => 'Question 2',
            'option_a' => 'A2',
            'option_b' => 'B2',
            'option_c' => 'C2',
            'option_d' => 'D2',
            'correct_option' => 'b',
        ]);

        $response = $this->actingAs($student)->postJson(route('student.lessons.quiz.submit', $lesson), [
            'answers' => [
                $q1->id => 'a',
                $q2->id => 'd',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('percentage', 50);

        $this->assertDatabaseHas('lesson_phase_analytics', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'evaluate',
            'evaluation_final_score' => 50,
        ]);
    }

    private function userPayload(string $name, string $role, string $email): array
    {
        return [
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
        ];
    }
}
