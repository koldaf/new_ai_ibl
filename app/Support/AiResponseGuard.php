<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Catches a specific small-model failure mode: instead of reasoning about the
 * student's answer, the model echoes fragments of its own JSON-schema
 * instructions back as the "feedback" or "follow_up" value (e.g. a response
 * ending in "...or null" lifted straight from the prompt template). Rejecting
 * these forces the caller to fall back to the rule-based classifier rather
 * than showing garbled text to a student.
 */
final class AiResponseGuard
{
    private const LEAKAGE_MARKERS = [
        'or null',
        '"classification"',
        '"follow_up"',
        '"feedback"',
        '"confidence"',
        '{"',
        '"}',
        'no markdown',
        'no explanation',
        'return only',
        'return exactly',
    ];

    public static function looksLikeTemplateLeakage(string $text): bool
    {
        $lower = Str::lower($text);

        foreach (self::LEAKAGE_MARKERS as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Weak, deliberately conservative check: only flags a "correct" verdict
     * whose answer text contains none of the lesson's extracted context
     * keywords at all. It will NOT catch an answer that uses on-topic words
     * while stating the wrong idea (e.g. inverting "energy cannot be
     * created" into "energy is created") — that requires a capable-enough
     * classification model, not a keyword check.
     */
    public static function sharesNoKeywords(string $answer, array $contextKeywords): bool
    {
        if ($contextKeywords === []) {
            return false;
        }

        $answerLower = Str::lower($answer);

        foreach ($contextKeywords as $keyword) {
            if (str_contains($answerLower, $keyword)) {
                return false;
            }
        }

        return true;
    }
}
