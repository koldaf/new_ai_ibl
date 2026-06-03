<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonPhaseAnalytic;
use App\Models\User;

class InquiryPhaseAnalyticsService
{
    private const INACTIVITY_CAP_SECONDS = 600;

    /**
     * Ensure a stage analytics row exists and update activity timestamps.
     */
    public function touchStage(User $user, Lesson $lesson, string $stage): LessonPhaseAnalytic
    {
        $analytic = $this->findOrCreate($user, $lesson, $stage);

        $now = now();
        if (is_null($analytic->started_at)) {
            $analytic->started_at = $now;
        }

        if (! is_null($analytic->completed_at)) {
            $analytic->last_active_at = $now;
            $analytic->save();

            return $analytic;
        }

        if ($analytic->last_active_at) {
            $delta = $analytic->last_active_at->diffInSeconds($now);

            // Ignore stale tab intervals so long idle sessions don't inflate active time.
            if ($delta > 0 && $delta <= self::INACTIVITY_CAP_SECONDS) {
                $analytic->time_spent_seconds += $delta;
            }
        }

        $analytic->last_active_at = $now;
        $analytic->save();

        return $analytic;
    }

    /**
     * Increment learner question/evidence counters for the stage.
     */
    public function recordQuestion(User $user, Lesson $lesson, string $stage, int $evidenceCount = 0): LessonPhaseAnalytic
    {
        $analytic = $this->touchStage($user, $lesson, $stage);
        $analytic->questions_generated += 1;

        if ($evidenceCount > 0) {
            $analytic->evidence_sources_consulted += $evidenceCount;
        }

        $analytic->save();

        return $analytic;
    }

    /**
     * Mark a stage complete and finalize tracked time.
     */
    public function markStageComplete(User $user, Lesson $lesson, string $stage): LessonPhaseAnalytic
    {
        $analytic = $this->touchStage($user, $lesson, $stage);

        if (is_null($analytic->completed_at)) {
            $analytic->completed_at = now();
            $analytic->save();
        }

        return $analytic;
    }

    /**
     * Persist reflection text and compute auto/final reflection quality.
     */
    public function saveReflection(User $user, Lesson $lesson, string $stage, string $reflectionText): LessonPhaseAnalytic
    {
        $analytic = $this->touchStage($user, $lesson, $stage);

        $autoQuality = $this->scoreReflectionQuality($reflectionText);

        $analytic->reflection_text = trim($reflectionText);
        $analytic->reflection_quality_auto = $autoQuality;
        $analytic->reflection_quality_final = $analytic->reflection_quality_teacher ?? $autoQuality;
        $analytic->save();

        return $analytic;
    }

    /**
     * Allow a teacher to override auto reflection quality.
     */
    public function setTeacherReflectionScore(User $student, Lesson $lesson, string $stage, int $score): LessonPhaseAnalytic
    {
        $analytic = $this->findOrCreate($student, $lesson, $stage);

        $normalized = max(0, min(100, $score));
        $analytic->reflection_quality_teacher = $normalized;
        $analytic->reflection_quality_final = $normalized;
        $analytic->save();

        return $analytic;
    }

    /**
     * Derive how many evidence sources were consulted from a model response.
     */
    public function deriveEvidenceCountFromResponse(string $answer): int
    {
        $text = trim($answer);
        if ($text === '') {
            return 0;
        }

        $matches = [];
        preg_match_all('/\[(\d+)\]/', $text, $matches);
        $bracketRefs = isset($matches[1]) ? array_unique($matches[1]) : [];

        preg_match_all('/https?:\/\/[^\s)]+/i', $text, $matches);
        $links = isset($matches[0]) ? array_unique($matches[0]) : [];

        preg_match_all('/\b(source|reference|citation)s?\b/i', $text, $matches);
        $keywords = isset($matches[0]) ? $matches[0] : [];

        $count = count($bracketRefs) + count($links);

        if ($count === 0 && count($keywords) > 0) {
            $count = 1;
        }

        return min($count, 10);
    }

    /**
     * 0-100 heuristic score based on structure, reasoning, evidence, and self-evaluation.
     */
    public function scoreReflectionQuality(string $reflectionText): int
    {
        $text = trim($reflectionText);
        if ($text === '') {
            return 0;
        }

        $length = mb_strlen($text);
        $wordCount = str_word_count(strip_tags($text));

        $reasoningHits = $this->keywordHits($text, [
            'because', 'therefore', 'so that', 'as a result', 'which means', 'hence', 'if', 'then', 'however',
        ]);

        $evidenceHits = $this->keywordHits($text, [
            'evidence', 'source', 'data', 'observed', 'experiment', 'example', 'result', 'measured',
        ]);

        $selfEvalHits = $this->keywordHits($text, [
            'i learned', 'i think', 'i realized', 'next time', 'i need to', 'i was wrong', 'i improved', 'i understand',
        ]);

        $lengthScore = 0;
        if ($length >= 450 || $wordCount >= 90) {
            $lengthScore = 30;
        } elseif ($length >= 250 || $wordCount >= 50) {
            $lengthScore = 22;
        } elseif ($length >= 120 || $wordCount >= 25) {
            $lengthScore = 14;
        } else {
            $lengthScore = 6;
        }

        $reasoningScore = min($reasoningHits * 6, 30);
        $evidenceScore = min($evidenceHits * 6, 24);
        $selfEvalScore = min($selfEvalHits * 4, 16);

        $score = $lengthScore + $reasoningScore + $evidenceScore + $selfEvalScore;

        return max(0, min(100, (int) round($score)));
    }

    private function findOrCreate(User $user, Lesson $lesson, string $stage): LessonPhaseAnalytic
    {
        return LessonPhaseAnalytic::firstOrCreate(
            [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'stage' => $stage,
            ],
            [
                'started_at' => now(),
                'last_active_at' => now(),
            ]
        );
    }

    private function keywordHits(string $text, array $keywords): int
    {
        $hits = 0;
        foreach ($keywords as $keyword) {
            if (mb_stripos($text, $keyword) !== false) {
                $hits++;
            }
        }

        return $hits;
    }
}
