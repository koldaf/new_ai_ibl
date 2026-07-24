<?php

namespace Tests\Feature\Admin;

use App\Models\AiPerformanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiPerformanceExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function students_cannot_export_the_performance_log(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('admin.ai-performance.export'))
            ->assertStatus(403);
    }

    #[Test]
    public function it_exports_the_full_log_as_csv_with_the_expected_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        AiPerformanceLog::create([
            'caller' => 'rag_query',
            'stage' => 'explain',
            'model_name' => 'qwen2.5:0.5b',
            'question_snippet' => 'What powers a torch?',
            'response_time_ms' => 5200,
            'ttft_ms' => 1800.5,
            'total_duration_ms' => 5190.2,
            'load_duration_ms' => 150.1,
            'prompt_tokens' => 650,
            'tokens_generated' => 40,
            'tokens_per_second' => 22.5,
            'done_reason' => 'stop',
            'context_chunks' => 3,
            'error' => null,
        ]);

        AiPerformanceLog::create([
            'caller' => 'engage_decision',
            'stage' => 'engage',
            'model_name' => 'qwen2.5:0.5b',
            'question_snippet' => null,
            'response_time_ms' => 300,
            'error' => 'Ollama unreachable',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.ai-performance.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", $csv))));

        $this->assertStringContainsString('Time,Caller,Stage,Model', $lines[0]);
        $this->assertStringContainsString('What powers a torch?', $csv);
        $this->assertStringContainsString('rag_query', $csv);
        $this->assertStringContainsString('Ollama unreachable', $csv);
        // Most recent first (latest id first, as ordered in the controller).
        $this->assertCount(3, $lines); // header + 2 rows
    }
}
