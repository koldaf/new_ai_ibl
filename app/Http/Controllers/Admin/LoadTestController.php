<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

class LoadTestController extends Controller
{
    private const MAX_CONCURRENCY = 50;

    public function index()
    {
        $lessons = Lesson::query()
            ->where('processing_status', 'completed')
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('dashboard.admin.load_test.index', [
            'lessons' => $lessons,
            'maxConcurrency' => self::MAX_CONCURRENCY,
        ]);
    }

    public function run(Request $request)
    {
        $validated = $request->validate([
            'lesson_id' => 'required|integer|exists:lessons,id',
            'question' => 'required|string|max:1000',
            'concurrency' => 'required|integer|min:1|max:' . self::MAX_CONCURRENCY,
        ]);

        $lessons = Lesson::query()
            ->where('processing_status', 'completed')
            ->orderBy('title')
            ->get(['id', 'title']);

        // These runs are CPU/Ollama-bound and can take minutes at higher concurrency
        // on modest hardware — the web-server-level timeout (PHP-FPM/Apache) still
        // applies and must be raised separately for this route if it's hit.
        set_time_limit(600);

        $concurrency = (int) $validated['concurrency'];
        $lessonId = (int) $validated['lesson_id'];
        $question = $validated['question'];

        $batchStart = microtime(true);

        $pool = Process::pool(function (Pool $pool) use ($concurrency, $lessonId, $question) {
            for ($i = 1; $i <= $concurrency; $i++) {
                $pool->as((string) $i)
                    ->timeout(300)
                    ->command([
                        PHP_BINARY,
                        base_path('artisan'),
                        'rag:loadtest-single',
                        (string) $lessonId,
                        $question,
                    ]);
            }
        })->start();

        $poolResults = $pool->wait();
        $batchWallMs = round((microtime(true) - $batchStart) * 1000, 2);

        $rows = [];
        $totalGenTokens = 0;
        $successCount = 0;
        $ttftValues = [];
        $wallValues = [];

        foreach ($poolResults->collect() as $key => $result) {
            $decoded = $result->successful() ? json_decode($result->output(), true) : null;

            if (! is_array($decoded)) {
                $rows[] = [
                    'user' => $key,
                    'ok' => false,
                    'error' => trim($result->errorOutput()) ?: 'Process failed with no output',
                ];
                continue;
            }

            if (! ($decoded['ok'] ?? false)) {
                $rows[] = [
                    'user' => $key,
                    'ok' => false,
                    'error' => $decoded['error'] ?? 'Unknown error',
                    'wall_ms' => $decoded['wall_ms'] ?? null,
                ];
                continue;
            }

            $successCount++;
            $totalGenTokens += (int) ($decoded['gen_tokens'] ?? 0);
            if (isset($decoded['ttft_ms'])) {
                $ttftValues[] = $decoded['ttft_ms'];
            }
            if (isset($decoded['wall_ms'])) {
                $wallValues[] = $decoded['wall_ms'];
            }

            $rows[] = [
                'user' => $key,
                'ok' => true,
                'prompt_tokens' => $decoded['prompt_tokens'] ?? null,
                'gen_tokens' => $decoded['gen_tokens'] ?? null,
                'ttft_ms' => $decoded['ttft_ms'] ?? null,
                'tps' => $decoded['tps'] ?? null,
                'wall_ms' => $decoded['wall_ms'] ?? null,
            ];
        }

        usort($rows, fn ($a, $b) => (int) $a['user'] <=> (int) $b['user']);

        $summary = [
            'concurrency' => $concurrency,
            'success_count' => $successCount,
            'failure_count' => $concurrency - $successCount,
            'batch_wall_ms' => $batchWallMs,
            'aggregate_tps' => $totalGenTokens > 0 ? round($totalGenTokens / ($batchWallMs / 1000), 2) : 0,
            'total_gen_tokens' => $totalGenTokens,
            'avg_ttft_ms' => count($ttftValues) > 0 ? round(array_sum($ttftValues) / count($ttftValues)) : null,
            'max_ttft_ms' => count($ttftValues) > 0 ? max($ttftValues) : null,
            'avg_wall_ms' => count($wallValues) > 0 ? round(array_sum($wallValues) / count($wallValues)) : null,
            'max_wall_ms' => count($wallValues) > 0 ? max($wallValues) : null,
        ];

        return view('dashboard.admin.load_test.index', [
            'lessons' => $lessons,
            'maxConcurrency' => self::MAX_CONCURRENCY,
            'results' => $rows,
            'summary' => $summary,
            'formLessonId' => $lessonId,
            'formQuestion' => $question,
            'formConcurrency' => $concurrency,
        ]);
    }
}
