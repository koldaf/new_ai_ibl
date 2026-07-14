<?php

namespace Tests\Feature\Admin;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoadTestControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function students_cannot_access_the_load_test_page(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('admin.load-test.index'))
            ->assertStatus(403);
    }

    #[Test]
    public function it_shows_a_message_when_no_lessons_are_ready(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.load-test.index'))
            ->assertOk()
            ->assertSee('No lessons are ready');
    }

    #[Test]
    public function it_lists_only_completed_lessons(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $ready = Lesson::create(['title' => 'Ready Lesson', 'description' => 'Test lesson', 'processing_status' => 'completed']);
        Lesson::create(['title' => 'Pending Lesson', 'description' => 'Test lesson', 'processing_status' => 'pending']);

        $response = $this->actingAs($admin)->get(route('admin.load-test.index'));

        $response->assertOk()
            ->assertSee('Ready Lesson')
            ->assertDontSee('Pending Lesson');
    }

    #[Test]
    public function it_runs_a_load_test_and_aggregates_per_user_results(): void
    {
        Process::fake([
            '*rag:loadtest-single*' => Process::sequence()
                ->push(json_encode(['ok' => true, 'wall_ms' => 15000, 'prompt_tokens' => 800, 'gen_tokens' => 60, 'ttft_ms' => 9000, 'tps' => 25.5]))
                ->push(json_encode(['ok' => true, 'wall_ms' => 16000, 'prompt_tokens' => 800, 'gen_tokens' => 55, 'ttft_ms' => 9500, 'tps' => 24.0]))
                ->push(json_encode(['ok' => false, 'wall_ms' => 500, 'error' => 'Ollama service is not responding'])),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $lesson = Lesson::create(['title' => 'Ready Lesson', 'description' => 'Test lesson', 'processing_status' => 'completed']);

        $response = $this->actingAs($admin)->post(route('admin.load-test.run'), [
            'lesson_id' => $lesson->id,
            'question' => 'What is the main idea?',
            'concurrency' => 3,
        ]);

        $response->assertOk()
            ->assertSee('2/3')
            ->assertSee('FAILED')
            ->assertSee('Ollama service is not responding');

        Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'rag:loadtest-single'));
    }

    #[Test]
    public function it_rejects_concurrency_above_the_configured_maximum(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lesson = Lesson::create(['title' => 'Ready Lesson', 'description' => 'Test lesson', 'processing_status' => 'completed']);

        $this->actingAs($admin)->post(route('admin.load-test.run'), [
            'lesson_id' => $lesson->id,
            'question' => 'What is the main idea?',
            'concurrency' => 999,
        ])->assertSessionHasErrors('concurrency');
    }
}
