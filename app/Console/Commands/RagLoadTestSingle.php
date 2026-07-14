<?php

namespace App\Console\Commands;

use App\Services\RagQueryService;
use Illuminate\Console\Command;

class RagLoadTestSingle extends Command
{
    protected $signature = 'rag:loadtest-single {lesson : Lesson ID} {question : The question to ask} {--stage=explore : Lesson stage to answer as}';

    protected $description = 'Run a single real RAG query (vector search + Ollama call) and print timing as JSON. Intended to be run as a subprocess by the admin load-test tool, one per simulated user.';

    public function handle(RagQueryService $ragService): int
    {
        $lessonId = (int) $this->argument('lesson');
        $question = (string) $this->argument('question');
        $stage = (string) $this->option('stage');

        $start = microtime(true);

        try {
            $answer = $ragService->generateResponse(
                query: $question,
                lessonId: $lessonId,
                stage: $stage,
                userName: 'Load Test',
                topK: 3,
            );

            $wallMs = round((microtime(true) - $start) * 1000, 2);
            $metrics = $ragService->getLastCallMetrics() ?? [];

            $this->line(json_encode(array_merge([
                'ok' => true,
                'wall_ms' => $wallMs,
                'answer_length' => strlen($answer),
            ], $metrics)));
        } catch (\Throwable $e) {
            $wallMs = round((microtime(true) - $start) * 1000, 2);

            $this->line(json_encode([
                'ok' => false,
                'wall_ms' => $wallMs,
                'error' => $e->getMessage(),
            ]));
        }

        return self::SUCCESS;
    }
}
