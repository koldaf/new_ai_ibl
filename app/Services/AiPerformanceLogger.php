<?php

namespace App\Services;

use App\Models\AiPerformanceLog;
use Illuminate\Support\Facades\Log;

/**
 * Static helper that persists one AI performance log entry per LLM call.
 *
 * Wrapped entirely in try/catch — a logging failure must never surface to the user.
 *
 * Ollama response data keys used:
 *   model               string
 *   prompt_eval_count   int    — number of tokens in the prompt
 *   prompt_eval_duration long  — nanoseconds spent evaluating the prompt (TTFT proxy)
 *   eval_count          int    — number of tokens generated
 *   eval_duration       long  — nanoseconds spent generating tokens
 *   total_duration      long  — nanoseconds total
 *   load_duration       long  — nanoseconds loading the model
 */
class AiPerformanceLogger
{
    /**
     * @param array       $ollamaData   The decoded JSON response body from Ollama
     * @param float       $wallClockMs  Wall-clock time from request send to response received (milliseconds)
     * @param array       $context      Caller metadata:
     *                                    'caller'           string  required
     *                                    'lesson_id'        int     optional
     *                                    'user_id'          int     optional
     *                                    'stage'            string  optional
     *                                    'question_snippet' string  optional  (will be truncated to 255)
     *                                    'context_chunks'   int     optional
     *                                    'error'            string  optional  (null = success)
     */
    public static function log(array $ollamaData, float $wallClockMs, array $context = []): void
    {
        try {
            // ── Ollama internal timings (nanoseconds → milliseconds) ──────────────
            $promptEvalDurationNs = $ollamaData['prompt_eval_duration'] ?? null;
            $evalDurationNs       = $ollamaData['eval_duration']        ?? null;
            $totalDurationNs      = $ollamaData['total_duration']       ?? null;
            $loadDurationNs       = $ollamaData['load_duration']        ?? null;

            // Allow callers (e.g. streaming) to supply a measured wall-clock TTFT instead
            $ttftMs = isset($context['ttft_ms'])
                ? (float) $context['ttft_ms']
                : ($promptEvalDurationNs !== null ? round($promptEvalDurationNs / 1_000_000, 2) : null);
            $totalDurationMs = $totalDurationNs      !== null ? round($totalDurationNs      / 1_000_000, 2) : null;
            $loadDurationMs  = $loadDurationNs       !== null ? round($loadDurationNs       / 1_000_000, 2) : null;

            // ── Token counts ──────────────────────────────────────────────────────
            $promptTokens    = isset($ollamaData['prompt_eval_count']) ? (int) $ollamaData['prompt_eval_count'] : null;
            $tokensGenerated = isset($ollamaData['eval_count'])        ? (int) $ollamaData['eval_count']        : null;

            // ── Tokens-per-second: eval_count ÷ (eval_duration in seconds) ────────
            $tps = null;
            if ($tokensGenerated !== null && $evalDurationNs !== null && $evalDurationNs > 0) {
                $tps = round($tokensGenerated / ($evalDurationNs / 1_000_000_000), 2);
            }

            // ── Model name (prefer Ollama's confirmed value, fall back to context) ─
            $modelName = $ollamaData['model'] ?? ($context['model_name'] ?? 'unknown');

            // ── Question snippet ──────────────────────────────────────────────────
            $snippet = isset($context['question_snippet'])
                ? mb_substr((string) $context['question_snippet'], 0, 255)
                : null;

            AiPerformanceLog::create([
                'caller'           => $context['caller']        ?? 'unknown',
                'lesson_id'        => $context['lesson_id']     ?? null,
                'user_id'          => $context['user_id']       ?? null,
                'stage'            => $context['stage']         ?? null,
                'model_name'       => $modelName,
                'question_snippet' => $snippet,
                'response_time_ms' => (int) round($wallClockMs),
                'ttft_ms'          => $ttftMs,
                'total_duration_ms'=> $totalDurationMs,
                'load_duration_ms' => $loadDurationMs,
                'prompt_tokens'    => $promptTokens,
                'tokens_generated' => $tokensGenerated,
                'tokens_per_second'=> $tps,
                'context_chunks'   => $context['context_chunks'] ?? null,
                'error'            => $context['error']          ?? null,
            ]);
        } catch (\Throwable $e) {
            // Never let a monitoring failure crash the application
            Log::warning('[AiPerformanceLogger] Failed to write performance log', [
                'error'   => $e->getMessage(),
                'caller'  => $context['caller'] ?? 'unknown',
            ]);
        }
    }

    /**
     * Log a failed LLM call (no Ollama data available).
     */
    public static function logError(float $wallClockMs, array $context, string $errorMessage): void
    {
        self::log([], $wallClockMs, array_merge($context, ['error' => $errorMessage]));
    }
}
