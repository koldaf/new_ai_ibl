<?php

namespace Tests\Unit;

use App\Models\AiPerformanceLog;
use App\Services\AiPerformanceLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiPerformanceLoggerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_done_reason_from_the_ollama_response(): void
    {
        AiPerformanceLogger::log([
            'model' => 'qwen2.5:0.5b',
            'prompt_eval_count' => 650,
            'eval_count' => 150,
            'eval_duration' => 5_000_000_000,
            'done_reason' => 'length',
        ], 5200.0, ['caller' => 'rag_query']);

        $log = AiPerformanceLog::latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('length', $log->done_reason);
    }

    #[Test]
    public function it_records_a_natural_stop_reason(): void
    {
        AiPerformanceLogger::log([
            'model' => 'qwen2.5:0.5b',
            'prompt_eval_count' => 650,
            'eval_count' => 40,
            'eval_duration' => 1_500_000_000,
            'done_reason' => 'stop',
        ], 1800.0, ['caller' => 'rag_query']);

        $log = AiPerformanceLog::latest()->first();

        $this->assertSame('stop', $log->done_reason);
    }

    #[Test]
    public function it_leaves_done_reason_null_when_ollama_did_not_provide_it(): void
    {
        AiPerformanceLogger::log([], 100.0, ['caller' => 'rag_query', 'error' => 'timed out']);

        $log = AiPerformanceLog::latest()->first();

        $this->assertNull($log->done_reason);
    }
}
