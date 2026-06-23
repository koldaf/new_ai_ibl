<?php

namespace App\Services;

use Illuminate\Support\Str;

class BloomTaxonomyClassifier
{
    private const LEVELS = ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'];

    private const STAGE_BASELINE = [
        'engage' => 'understand',
        'explore' => 'analyze',
        'explain' => 'understand',
        'elaborate' => 'apply',
        'evaluate' => 'evaluate',
    ];

    private const PATTERNS = [
        'create' => [
            '/\b(create|design|develop|compose|construct|formulate|propose|invent|build)\b/i',
        ],
        'evaluate' => [
            '/\b(evaluate|assess|justify|defend|critique|argue|recommend|judge|best|better|worth)\b/i',
        ],
        'analyze' => [
            '/\b(analyze|analyse|compare|differentiate|distinguish|examine|investigate|infer|relationship|pattern|cause|effect|why)\b/i',
        ],
        'apply' => [
            '/\b(apply|use|solve|demonstrate|calculate|implement|show\s+how|perform)\b/i',
        ],
        'understand' => [
            '/\b(explain|summarize|describe|interpret|paraphrase|discuss|clarify|meaning)\b/i',
        ],
        'remember' => [
            '/\b(define|list|identify|name|recall|state|what\s+is|who\s+is|when\s+did)\b/i',
        ],
    ];

    public function classify(string $question, ?string $stage = null): array
    {
        $text = trim($question);

        if ($text === '' || Str::startsWith($text, '__')) {
            return [
                'bloom_level' => null,
                'bloom_confidence' => null,
            ];
        }

        foreach (self::PATTERNS as $level => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text) === 1) {
                    return [
                        'bloom_level' => $level,
                        'bloom_confidence' => 0.86,
                    ];
                }
            }
        }

        $baseline = self::STAGE_BASELINE[$stage ?? ''] ?? 'understand';

        return [
            'bloom_level' => $baseline,
            'bloom_confidence' => 0.62,
        ];
    }

    public function isValidLevel(?string $level): bool
    {
        return in_array($level, self::LEVELS, true);
    }
}
