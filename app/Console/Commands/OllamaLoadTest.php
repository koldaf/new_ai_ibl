<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OllamaLoadTest extends Command
{
    protected $signature = 'ollama:loadtest
        {--concurrency=5 : Number of simultaneous requests to fire at Ollama}
        {--model= : Model to use (defaults to config ollama.llm_model)}
        {--tokens=150 : num_predict per request}
        {--chars=7000 : Approx prompt length in characters when no --prompt is given (mirrors a real classification prompt)}
        {--prompt= : Use this exact prompt text instead of the synthetic one}
        {--skip-warmup : Skip the single warmup request that forces the model to load before timing}';

    protected $description = 'Fire N concurrent requests directly at Ollama to measure real concurrency throughput/latency, instead of single-user numbers';

    public function handle(): int
    {
        $concurrency = max(1, (int) $this->option('concurrency'));
        $model = $this->option('model') ?: config('ollama.llm_model');
        $maxTokens = max(1, (int) $this->option('tokens'));
        $baseUrl = rtrim(config('ollama.base_url', 'http://localhost:11434'), '/');
        $prompt = $this->option('prompt') ?: $this->syntheticPrompt((int) $this->option('chars'));

        $this->info("Model: {$model}");
        $this->info('Prompt length: ' . strlen($prompt) . ' chars');
        $this->info("num_predict: {$maxTokens}");

        if (! $this->option('skip-warmup')) {
            $this->info('Warming up (forcing model load, not timed)...');

            try {
                $warmup = Http::timeout(300)->post("{$baseUrl}/api/generate", [
                    'model' => $model,
                    'prompt' => 'Say OK.',
                    'stream' => false,
                    'options' => ['num_predict' => 5],
                ]);
            } catch (\Throwable $e) {
                $this->error("Could not reach Ollama at {$baseUrl}: {$e->getMessage()}");
                return self::FAILURE;
            }

            if ($warmup->failed()) {
                $this->error("Warmup request failed: HTTP {$warmup->status()} — {$warmup->body()}");
                return self::FAILURE;
            }
        }

        $this->info("Firing {$concurrency} concurrent requests at {$baseUrl}...");

        $wallStart = microtime(true);

        $responses = Http::pool(fn ($pool) => collect(range(1, $concurrency))
            ->map(fn (int $i) => $pool->as((string) $i)
                ->timeout(300)
                ->post("{$baseUrl}/api/generate", [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.1,
                        'num_predict' => $maxTokens,
                    ],
                ]))
            ->all());

        $wallElapsedMs = (microtime(true) - $wallStart) * 1000;

        $rows = [];
        $totalGenTokens = 0;
        $ttftValues = [];

        foreach ($responses as $key => $response) {
            if (! $response instanceof Response) {
                $message = $response instanceof \Throwable ? $response->getMessage() : 'unknown error';
                $rows[] = [$key, 'ERROR', '-', '-', '-', '-', '-', $message];
                continue;
            }

            if ($response->failed()) {
                $rows[] = [$key, "HTTP {$response->status()}", '-', '-', '-', '-', '-', $response->body()];
                continue;
            }

            $data = $response->json();
            $promptTokens = (int) ($data['prompt_eval_count'] ?? 0);
            $genTokens = (int) ($data['eval_count'] ?? 0);
            $promptEvalMs = round(($data['prompt_eval_duration'] ?? 0) / 1_000_000);
            $evalMs = round(($data['eval_duration'] ?? 0) / 1_000_000);
            $totalMs = round(($data['total_duration'] ?? 0) / 1_000_000);
            $tps = $genTokens > 0 && ($data['eval_duration'] ?? 0) > 0
                ? round($genTokens / ($data['eval_duration'] / 1_000_000_000), 2)
                : 0;

            $totalGenTokens += $genTokens;
            $ttftValues[] = $promptEvalMs;

            $rows[] = [$key, 'OK', $promptTokens, $genTokens, "{$promptEvalMs} ms", "{$evalMs} ms", $tps, "{$totalMs} ms"];
        }

        $this->newLine();
        $this->table(
            ['#', 'Status', 'Prompt tk', 'Gen tk', 'TTFT (prefill)', 'Gen time', 'TPS', 'Total'],
            $rows
        );

        $aggregateTps = $totalGenTokens > 0 ? round($totalGenTokens / ($wallElapsedMs / 1000), 2) : 0;
        $avgTtft = count($ttftValues) > 0 ? round(array_sum($ttftValues) / count($ttftValues)) : 0;
        $maxTtft = count($ttftValues) > 0 ? max($ttftValues) : 0;

        $this->newLine();
        $this->info('Wall clock for all requests to finish: ' . round($wallElapsedMs) . ' ms');
        $this->info("Aggregate throughput: {$totalGenTokens} generated tokens / " . round($wallElapsedMs / 1000, 1) . 's = ' . $aggregateTps . ' tok/s across all requests combined');
        $this->info("Avg TTFT (prefill) under concurrency: {$avgTtft} ms — max: {$maxTtft} ms");
        $this->comment('Compare aggregate tok/s and per-request TTFT/TPS here against your single-user baseline to see how much the box degrades under load.');

        return self::SUCCESS;
    }

    private function syntheticPrompt(int $targetChars): string
    {
        $sentence = 'The water cycle describes how water moves through evaporation, condensation, and precipitation, transferring energy between the atmosphere, oceans, and land surfaces. ';

        $text = str_repeat($sentence, (int) ceil($targetChars / strlen($sentence)));
        $text = substr($text, 0, $targetChars);

        return $text . "\n\nClassify the student's understanding of this scenario as correct, partial, misconception, or off_topic. Return JSON only: {\"classification\":\"...\",\"confidence\":0.0,\"feedback\":\"...\",\"follow_up\":\"... or null\"}\n\nStudent answer: Energy moves between stores when things heat up or cool down.";
    }
}
