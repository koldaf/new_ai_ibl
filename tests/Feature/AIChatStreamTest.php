<?php

namespace Tests\Feature;

use App\Models\AiChatMessage;
use App\Models\Lesson;
use App\Models\User;
use App\Services\RagQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AIChatStreamTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_rejects_the_engage_stage_since_that_needs_structured_classification_not_free_text(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Student One', 'student', 'stream-one@example.test'));
        $lesson = Lesson::create(['title' => 'Energy', 'description' => 'Streaming test']);

        $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask-stream', $lesson), [
                'question' => 'What is energy?',
                'stage' => 'engage',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function it_streams_tokens_as_ndjson_and_persists_the_full_answer(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Student Two', 'student', 'stream-two@example.test'));
        $lesson = Lesson::create(['title' => 'Energy', 'description' => 'Streaming test']);

        $mock = Mockery::mock(RagQueryService::class);
        $mock->shouldReceive('generateResponseStream')
            ->once()
            ->withArgs(function (string $question, int $lessonId, string $stage) use ($lesson) {
                return $question === 'What powers a torch?'
                    && $lessonId === $lesson->id
                    && $stage === 'explain';
            })
            ->andReturnUsing(function () {
                return (function () {
                    yield 'Chemical ';
                    yield 'energy stored in the battery.';

                    return 'Chemical energy stored in the battery.';
                })();
            });

        $this->app->instance(RagQueryService::class, $mock);

        $response = $this->actingAs($student)
            ->post(route('student.lessons.ai.ask-stream', $lesson), [
                'question' => 'What powers a torch?',
                'stage' => 'explain',
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-ndjson');

        $lines = array_values(array_filter(explode("\n", $response->streamedContent())));
        $decoded = array_map(fn ($line) => json_decode($line, true), $lines);

        $this->assertSame('Chemical ', $decoded[0]['token']);
        $this->assertFalse($decoded[0]['done']);
        $this->assertSame('energy stored in the battery.', $decoded[1]['token']);
        $this->assertTrue(end($decoded)['done']);

        $chat = AiChatMessage::where('lesson_id', $lesson->id)->where('user_id', $student->id)->first();
        $this->assertNotNull($chat);
        $this->assertSame('Chemical energy stored in the battery.', $chat->answer);
        $this->assertSame('explain', $chat->stage);
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

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
