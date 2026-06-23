<?php

namespace Tests\Feature;

use App\Models\AiChatMessage;
use App\Models\AppSetting;
use App\Models\Lesson;
use App\Models\User;
use App\Services\RagQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AIChatMemoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function disabled_memory_limits_prompt_history_to_current_lesson(): void
    {
        AppSetting::putValue('ai_memory_enabled', false, 'boolean');

        $student = User::query()->forceCreate($this->userPayload('Student One', 'student', 'student-one@example.test'));
        [$currentLesson, $otherLesson] = $this->seedLessons();

        AiChatMessage::create([
            'user_id' => $student->id,
            'lesson_id' => $currentLesson->id,
            'stage' => 'explore',
            'question' => 'Current lesson question',
            'answer' => 'Current lesson answer',
        ]);

        AiChatMessage::create([
            'user_id' => $student->id,
            'lesson_id' => $otherLesson->id,
            'stage' => 'explore',
            'question' => 'Other lesson question',
            'answer' => 'Other lesson answer',
        ]);

        $mock = Mockery::mock(RagQueryService::class);
        $mock->shouldReceive('generateResponse')
            ->once()
            ->withArgs(function (
                string $question,
                int $lessonId,
                string $stage,
                string $userName,
                int $topK,
                string $memoryContext,
                bool $memoryEnabled
            ) use ($currentLesson) {
                $this->assertSame('Can you connect this to the topic?', $question);
                $this->assertSame($currentLesson->id, $lessonId);
                $this->assertSame('explore', $stage);
                $this->assertSame('Student One', $userName);
                $this->assertSame(5, $topK);
                $this->assertFalse($memoryEnabled);
                $this->assertStringContainsString('Current lesson question', $memoryContext);
                $this->assertStringNotContainsString('Other lesson question', $memoryContext);

                return true;
            })
            ->andReturn('Lesson-only answer');

        $this->app->instance(RagQueryService::class, $mock);

        $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', ['lesson' => $currentLesson]), [
                'question' => 'Can you connect this to the topic?',
                'stage' => 'explore',
            ])
            ->assertOk()
            ->assertJsonPath('answer', 'Lesson-only answer');
    }

    #[Test]
    public function enabled_memory_includes_prior_interactions_from_other_lessons(): void
    {
        AppSetting::putValue('ai_memory_enabled', true, 'boolean');

        $student = User::query()->forceCreate($this->userPayload('Student One', 'student', 'student-one@example.test'));
        [$currentLesson, $otherLesson] = $this->seedLessons();

        AiChatMessage::create([
            'user_id' => $student->id,
            'lesson_id' => $otherLesson->id,
            'stage' => 'explain',
            'question' => 'Remind me about yesterday\'s concept',
            'answer' => 'It was about evaporation.',
        ]);

        $mock = Mockery::mock(RagQueryService::class);
        $mock->shouldReceive('generateResponse')
            ->once()
            ->withArgs(function (
                string $question,
                int $lessonId,
                string $stage,
                string $userName,
                int $topK,
                string $memoryContext,
                bool $memoryEnabled
            ) use ($currentLesson, $otherLesson) {
                $this->assertSame($currentLesson->id, $lessonId);
                $this->assertSame('explain', $stage);
                $this->assertSame('Student One', $userName);
                $this->assertTrue($memoryEnabled);
                $this->assertStringContainsString($otherLesson->title, $memoryContext);
                $this->assertStringContainsString('Remind me about yesterday\'s concept', $memoryContext);

                return true;
            })
            ->andReturn('Cross-lesson answer');

        $this->app->instance(RagQueryService::class, $mock);

        $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', ['lesson' => $currentLesson]), [
                'question' => 'How does this compare?',
                'stage' => 'explain',
            ])
            ->assertOk()
            ->assertJsonPath('answer', 'Cross-lesson answer');
    }

    private function seedLessons(): array
    {
        $currentLesson = Lesson::create([
            'title' => 'States of Matter',
            'description' => 'Current lesson',
        ]);

        $otherLesson = Lesson::create([
            'title' => 'Water Cycle',
            'description' => 'Previous lesson',
        ]);

        return [$currentLesson, $otherLesson];
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