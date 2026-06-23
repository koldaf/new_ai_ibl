<?php

namespace Tests\Unit;

use App\Models\AiChatMessage;
use App\Models\Lesson;
use App\Models\LessonCheckpointQuestion;
use App\Models\User;
use App\Services\RagQueryService;
use App\Services\StageCheckpointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StageCheckpointServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_avoids_repeating_the_last_teacher_authored_checkpoint_question_when_possible(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Student One', 'student', 'student-one@example.test'));
        $lesson = Lesson::create([
            'title' => 'Forces',
            'description' => 'Checkpoint lesson',
        ]);

        LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question_text' => 'What did you observe first?',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question_text' => 'Which pattern seems strongest?',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        AiChatMessage::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question' => '__checkpoint_start__',
            'answer' => 'What did you observe first?',
        ]);

        $ragService = Mockery::mock(RagQueryService::class);
        $service = new StageCheckpointService($ragService);

        $result = $service->generateCheckpointQuestion($lesson, 'explore', $student);

        $this->assertSame('Which pattern seems strongest?', $result['answer']);
        $this->assertSame('in_progress', $result['engage_status']);
    }

    #[Test]
    public function it_keeps_short_fallback_feedback_within_the_socratic_limit(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Student Two', 'student', 'student-two@example.test'));
        $lesson = Lesson::create([
            'title' => 'Heat Transfer',
            'description' => 'Checkpoint fallback test',
        ]);

        $ragService = Mockery::mock(RagQueryService::class);
        $service = new StageCheckpointService($ragService);

        $result = $service->evaluateCheckpointAnswer($lesson, $student, 'Maybe', 'explain');

        $this->assertSame('off_topic', $result['classification']);
        $this->assertLessThanOrEqual(30, str_word_count($result['answer']));
        $this->assertSame('Too short. What key idea supports your answer?', $result['answer']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
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