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
        $this->assertSame('teacher_question', $result['context_source']);
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

    #[Test]
    public function it_rejects_an_llm_classification_that_leaks_prompt_template_text(): void
    {
        // Reproduces the real bug: a student answer that inverts the actual law
        // ("energy is created" instead of "cannot be created or destroyed") got
        // graded "correct" at high confidence, with feedback that ended in the
        // literal "...or null" fragment lifted from the JSON schema instructions.
        $lesson = Lesson::create([
            'title' => 'Energy',
            'description' => 'Checkpoint guardrail test',
        ]);

        $ragService = Mockery::mock(RagQueryService::class);
        $ragService->shouldReceive('getClassificationModel')->once()->andReturn('qwen2.5:0.5b');
        $ragService->shouldReceive('callLlm')->once()->andReturn(json_encode([
            'classification' => 'correct',
            'confidence' => 0.9,
            'feedback' => 'The student provided a detailed explanation using the law of conservation of energy to ...or null',
            'follow_up' => null,
        ]));

        $service = new StageCheckpointService($ragService);

        $method = new \ReflectionMethod(StageCheckpointService::class, 'classifyWithRag');
        $method->setAccessible(true);

        $result = $method->invoke(
            $service,
            $lesson,
            'the law states that all forms of energy are created somehow',
            'explain',
            'Student Three',
            'Energy conservation states energy cannot be created or destroyed, only transformed between forms.'
        );

        // Rejected outright — the caller (classifyAnswer) treats null as "fall
        // back to the rule-based classifier" rather than trusting this verdict.
        $this->assertNull($result);
    }

    #[Test]
    public function it_rejects_a_correct_verdict_that_shares_no_vocabulary_with_the_lesson_context(): void
    {
        $lesson = Lesson::create([
            'title' => 'Energy',
            'description' => 'Checkpoint guardrail test',
        ]);

        $ragService = Mockery::mock(RagQueryService::class);
        $ragService->shouldReceive('getClassificationModel')->once()->andReturn('qwen2.5:0.5b');
        $ragService->shouldReceive('callLlm')->once()->andReturn(json_encode([
            'classification' => 'correct',
            'confidence' => 0.95,
            'feedback' => 'Great answer, well done!',
            'follow_up' => null,
        ]));

        $service = new StageCheckpointService($ragService);

        $method = new \ReflectionMethod(StageCheckpointService::class, 'classifyWithRag');
        $method->setAccessible(true);

        $result = $method->invoke(
            $service,
            $lesson,
            'I like pizza and video games on the weekend',
            'explain',
            'Student Four',
            'Energy conservation states energy cannot be created or destroyed, only transformed between forms.'
        );

        $this->assertNull($result);
    }

    #[Test]
    public function it_allows_a_full_conversational_reply_through_without_chopping_it_down(): void
    {
        $service = new StageCheckpointService(Mockery::mock(RagQueryService::class));

        $method = new \ReflectionMethod(StageCheckpointService::class, 'normalizeSocraticReply');
        $method->setAccessible(true);

        // 25-word feedback + 16-word follow-up — the actual conversational target,
        // well within the new limits. The old caps (14 words when a follow-up is
        // present) would have gutted this back down to a terse fragment.
        $feedback = implode(' ', array_fill(0, 25, 'word'));
        $followUp = implode(' ', array_fill(0, 16, 'ask'));

        [, , $resultFeedback, $resultFollowUp] = $method->invoke($service, 'partial', 0.6, $feedback, $followUp);

        $this->assertSame($feedback, $resultFeedback);
        $this->assertSame($followUp, $resultFollowUp);
    }

    #[Test]
    public function it_still_caps_a_model_that_rambles_on_too_long(): void
    {
        $service = new StageCheckpointService(Mockery::mock(RagQueryService::class));

        $method = new \ReflectionMethod(StageCheckpointService::class, 'normalizeSocraticReply');
        $method->setAccessible(true);

        $feedback = implode(' ', array_fill(0, 50, 'word'));
        $followUp = implode(' ', array_fill(0, 16, 'ask'));

        [, , $resultFeedback] = $method->invoke($service, 'partial', 0.6, $feedback, $followUp);

        $this->assertSame(35, str_word_count($resultFeedback));
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