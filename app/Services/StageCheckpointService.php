<?php

namespace App\Services;

use App\Models\AiChatMessage;
use App\Models\Lesson;
use App\Models\LessonCheckpointCorpus;
use App\Models\LessonCheckpointQuestion;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StageCheckpointService
{
    private const CHECKPOINT_STAGES = ['explore', 'explain', 'elaborate'];

    private const STAGE_GOALS = [
        'explore' => [
            'goal' => 'scientific observation and critical thinking',
            'prompt_instruction' => 'Ask the student one open-ended question about what patterns, observations, or evidence they noticed during their exploration of the lesson material. Focus on the process of inquiry, not recall of facts.',
        ],
        'explain' => [
            'goal' => 'conceptual understanding in their own words',
            'prompt_instruction' => 'Ask the student to explain the core concept from this lesson in their own words, as if describing it to a peer. Avoid asking them to repeat text verbatim.',
        ],
        'elaborate' => [
            'goal' => 'application and transfer of the concept to a new situation',
            'prompt_instruction' => 'Present a brief novel scenario related to the lesson topic and ask the student how they would apply or connect the concept they learned to this new situation.',
        ],
    ];

    public function __construct(private readonly RagQueryService $ragService)
    {
    }

    public function isCheckpointStage(string $stage): bool
    {
        return in_array($stage, self::CHECKPOINT_STAGES, true);
    }

    /**
     * Generate a checkpoint question for a student at the given stage.
     * Returns the same array shape as EngageDecisionService::generateStartQuestion().
     */
    public function generateCheckpointQuestion(Lesson $lesson, string $stage, User $user): array
    {
        $stageConfig = self::STAGE_GOALS[$stage] ?? self::STAGE_GOALS['explain'];
        $stageContent = optional($lesson->getStageContent($stage))->content;
        $contextText = trim(strip_tags((string) $stageContent));
        $userName = $user->name;

        $teacherQuestion = $this->selectTeacherQuestion($lesson, $stage, $user);
        if ($teacherQuestion !== null) {
            return [
                'answer' => $teacherQuestion->question_text,
                'engage_status' => 'in_progress',
                'context_source' => 'stage_text',
                'retrieval_mode' => 'non_vector',
            ];
        }

        if ($contextText === '' && !$this->ragService->isReady($lesson->id)) {
            $fallback = $this->buildFallbackQuestion($stage, null, $userName);
            return [
                'answer' => $fallback,
                'engage_status' => 'in_progress',
                'context_source' => 'none',
                'retrieval_mode' => 'none',
            ];
        }

        // If RAG is available, use it to generate a contextual question
        if ($this->ragService->isReady($lesson->id) && $this->ragService->isOllamaHealthy()) {
            $aiQuestion = $this->generateQuestionWithRag($lesson, $stage, $stageConfig, $userName);
            if ($aiQuestion !== null) {
                return [
                    'answer' => $aiQuestion,
                    'engage_status' => 'in_progress',
                    'context_source' => 'rag',
                    'retrieval_mode' => 'vector',
                ];
            }
        }

        // Fallback: derive a question from stage text content
        $question = $this->buildFallbackQuestion($stage, $contextText !== '' ? $contextText : null, $userName);

        return [
            'answer' => $question,
            'engage_status' => 'in_progress',
            'context_source' => $contextText !== '' ? 'stage_text' : 'none',
            'retrieval_mode' => $contextText !== '' ? 'non_vector' : 'none',
        ];
    }

    /**
     * Evaluate a student's checkpoint answer and return classification + feedback.
     * When classified as partial (explore stage), also generates the full correct answer.
     * Stops after 3 attempts or when full answer is displayed.
     */
    public function evaluateCheckpointAnswer(Lesson $lesson, User $user, string $answer, string $stage): array
    {
        $answer = trim($answer);
        $userName = $user->name;

        $history = AiChatMessage::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->where('stage', $stage)
            ->latest('id')
            ->take(10)
            ->get();

        // Get the original checkpoint question
        $checkpointQuestion = AiChatMessage::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->where('stage', $stage)
            ->where('question', '__checkpoint_start__')
            ->latest('id')
            ->first();

        // Count total student attempts (excluding the question itself)
        $totalAttempts = $history->filter(fn ($row) => $row->question !== '__checkpoint_start__')->count() + 1;

        // Detect if student pasted back the question
        if ($checkpointQuestion && $this->isSimilarToQuestion($answer, $checkpointQuestion->answer)) {
            return [
                'classification' => 'question_repetition',
                'confidence' => 1.0,
                'feedback_text' => 'That\'s the question I asked! Please read the lesson content carefully and provide your own answer.',
                'follow_up_question' => null,
                'engage_status' => 'in_progress', // Allow them to try again
                'completion_reason' => 'question_repeated',
                'turn_index' => $totalAttempts,
                'context_source' => 'none',
                'retrieval_mode' => 'none',
                'answer' => 'That\'s the question I asked! Please read the lesson content carefully and provide your own answer.',
                'full_answer' => null,
            ];
        }

        // Check if we've reached 3 attempts limit
        if ($totalAttempts >= 3) {
            // Force completion after 3 attempts
            return [
                'classification' => 'attempts_exhausted',
                'confidence' => 1.0,
                'feedback_text' => 'You\'ve made three attempts. Great effort! Review the lesson material to deepen your understanding.',
                'follow_up_question' => null,
                'engage_status' => 'complete',
                'completion_reason' => 'max_attempts_reached',
                'turn_index' => $totalAttempts,
                'context_source' => 'none',
                'retrieval_mode' => 'none',
                'answer' => 'You\'ve made three attempts. Great effort! Review the lesson material to deepen your understanding.',
                'full_answer' => null,
            ];
        }

        $turnIndex = $totalAttempts;

        [$classification, $confidence, $feedback, $followUp] = $this->classifyAnswer(
            $lesson, $answer, $stage, $userName
        );

        $followupsUsed = $history->filter(fn ($row) => !empty($row->follow_up_question))->count();
        $needsFollowup = in_array($classification, ['partial', 'off_topic'], true);

        $engageStatus = 'in_progress';
        $completionReason = null;

        if ($classification === 'correct') {
            $engageStatus = 'complete';
            $completionReason = 'correct_response';
        } elseif ($needsFollowup && $followupsUsed >= 2) {
            $engageStatus = 'review_needed';
            $completionReason = 'max_followups_reached';
        } elseif ($classification === 'partial' && $followUp === null) {
            // partial with some completeness is acceptable
            $engageStatus = 'complete';
            $completionReason = 'acceptable_response';
        }

        $answerText = $followUp
            ? $feedback . ' ' . $followUp
            : $feedback;

        $hasCheckpointContext = LessonCheckpointCorpus::query()
            ->where('lesson_id', $lesson->id)
            ->where('stage', $stage)
            ->where('processing_status', 'completed')
            ->exists();

        // Generate full correct answer when classification is partial (for explore stage)
        // Once full_answer is shown, force completion to stop the chat
        $fullAnswer = null;
        if ($classification === 'partial' && $stage === 'explore') {
            $fullAnswer = $this->generateFullAnswer($lesson, $stage, $userName);
            // After showing full answer, force completion
            if ($fullAnswer !== null) {
                $engageStatus = 'complete';
                $completionReason = 'full_answer_provided';
            }
        }

        return [
            'classification' => $classification,
            'confidence' => $confidence,
            'feedback_text' => $feedback,
            'follow_up_question' => $followUp,
            'engage_status' => $engageStatus,
            'completion_reason' => $completionReason,
            'turn_index' => $turnIndex,
            'context_source' => $hasCheckpointContext ? 'rag' : 'stage_text',
            'retrieval_mode' => $hasCheckpointContext ? 'vector' : 'non_vector',
            'answer' => $answerText,
            'full_answer' => $fullAnswer,
        ];
    }

    /**
     * Check whether a student has completed the checkpoint for a stage.
     */
    public function hasCompletedCheckpoint(int $userId, int $lessonId, string $stage): bool
    {
        return AiChatMessage::query()
            ->where('user_id', $userId)
            ->where('lesson_id', $lessonId)
            ->where('stage', $stage)
            ->where('engage_status', 'complete')
            ->exists();
    }

    // ─────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────

    private function generateQuestionWithRag(Lesson $lesson, string $stage, array $stageConfig, string $userName): ?string
    {
        try {
            $context = $this->ragService->retrieveContextSafe($lesson, $stageConfig['goal'], 4);
            if ($context === '') {
                return null;
            }

            $system = 'You are a pedagogical assistant helping an AI-based inquiry learning system. ' .
                'Generate exactly one thoughtful, open-ended question. Return only the question text—no preamble, no numbering.';

            $prompt = "Stage goal: {$stageConfig['goal']}\n" .
                "Instruction: {$stageConfig['prompt_instruction']}\n\n" .
                "Lesson context:\n<CONTEXT>\n{$context}\n</CONTEXT>\n\n" .
                "The student's name is {$userName}. " .
                "Generate one short, open-ended checkpoint question addressed naturally to {$userName}.";

            $question = trim($this->ragService->callLlm($prompt, $system, 80, [
                'caller' => 'stage_checkpoint',
                'lesson_id' => $lesson->id,
                'stage' => $stage,
            ]));

            return $question !== '' ? $question : null;
        } catch (\Throwable $e) {
            Log::warning('[StageCheckpoint] RAG question generation failed, using fallback', [
                'lesson_id' => $lesson->id,
                'stage' => $stage,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function classifyAnswer(Lesson $lesson, string $answer, string $stage, string $userName): array
    {
        if (str_word_count($answer) < 4) {
            return ['off_topic', 0.30, 'Too short.', 'What key idea supports your answer?'];
        }

        $context = $this->retrieveCheckpointContext($lesson, $stage, $answer);

        if ($context !== '' && $this->ragService->isOllamaHealthy()) {
            $result = $this->classifyWithRag($lesson, $answer, $stage, $userName, $context);
            if ($result !== null) {
                return $this->normalizeSocraticReply(...$result);
            }
        }

        $stageContent = optional($lesson->getStageContent($stage))->content;
        $contextText = trim(strip_tags((string) $stageContent));
        $tokens = $this->extractKeywords($contextText);
        $answerLower = Str::lower($answer);
        $hits = count(array_filter($tokens, fn ($t) => str_contains($answerLower, $t)));
        $ratio = $tokens ? $hits / count($tokens) : 0;

        if ($ratio >= 0.25) {
            return ['correct', 0.72, 'Good link to the key idea.', null];
        }

        if ($ratio >= 0.10) {
            return ['partial', 0.58, 'Close, but incomplete.', 'What evidence supports that idea?'];
        }

        return ['off_topic', 0.40, 'Not quite the stage focus.', 'Which idea from this stage fits best?'];
    }

    private function classifyWithRag(Lesson $lesson, string $answer, string $stage, string $userName, string $context): ?array
    {
        try {
            $stageGoal = self::STAGE_GOALS[$stage]['goal'] ?? 'subject understanding';

            $system = 'You are an educational AI evaluating a student checkpoint response. ' .
                'Return only valid JSON. No markdown, no explanation.';

            $prompt = "Stage: {$stage}. Goal: evaluate {$stageGoal}.\n" .
                "Allowed classification values: correct, partial, off_topic.\n" .
                "Confidence: 0 to 1. Feedback: max 10 words.\n" .
                "Follow_up: one short Socratic question, max 12 words, if classification is NOT correct, otherwise null.\n" .
                "The student's name is {$userName}. Address them naturally in feedback.\n\n" .
                "Return exactly this JSON:\n" .
                "{\"classification\":\"correct|partial|off_topic\",\"confidence\":0.0,\"feedback\":\"...\",\"follow_up\":\"...or null\"}\n\n" .
                "Lesson context:\n<CONTEXT>\n{$context}\n</CONTEXT>\n\n" .
                "Student answer: {$answer}";

            $raw = trim($this->ragService->callLlm($prompt, $system, 200, [
                'caller' => 'stage_checkpoint',
                'lesson_id' => $lesson->id,
                'stage' => $stage,
                'question_snippet' => $answer,
            ]));

            $decoded = $this->decodeJson($raw);
            if (!is_array($decoded)) {
                return null;
            }

            $classification = (string) ($decoded['classification'] ?? '');
            if (!in_array($classification, ['correct', 'partial', 'off_topic'], true)) {
                return null;
            }

            $confidence = max(0.0, min(1.0, (float) ($decoded['confidence'] ?? 0.5)));
            $feedback = trim((string) ($decoded['feedback'] ?? ''));
            if ($feedback === '') {
                return null;
            }

            $followUp = isset($decoded['follow_up']) && is_string($decoded['follow_up'])
                ? trim($decoded['follow_up'])
                : null;
            $followUp = ($followUp === '' || $followUp === 'null') ? null : $followUp;

            return [$classification, $confidence, $feedback, $followUp];
        } catch (\Throwable $e) {
            Log::warning('[StageCheckpoint] RAG classification failed, using fallback', [
                'lesson_id' => $lesson->id,
                'stage' => $stage,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function decodeJson(string $raw): ?array
    {
        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw)) ?? trim($raw);
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;
        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);
        return is_array($decoded) ? $decoded : null;
    }

    private function buildFallbackQuestion(string $stage, ?string $contextText, string $userName): string
    {
        $topic = $contextText ? ($this->extractKeywords($contextText)[0] ?? 'the concept') : 'the concept';

        return match ($stage) {
            'explore' => "{$userName}, what patterns or observations did you make while exploring the material on {$topic}?",
            'explain' => "{$userName}, can you explain what you understood about {$topic} in your own words?",
            'elaborate' => "{$userName}, how would you apply your understanding of {$topic} to a new situation you might encounter?",
            default => "{$userName}, what did you take away from this stage of the lesson?",
        };
    }

    private function extractKeywords(string $text): array
    {
        $clean = Str::lower(strip_tags($text));
        $tokens = preg_split('/[^a-z0-9]+/', $clean) ?: [];

        $stop = [
            'this', 'that', 'with', 'from', 'your', 'what', 'when', 'where', 'which', 'about',
            'would', 'could', 'there', 'their', 'have', 'has', 'were', 'been', 'into', 'also',
            'then', 'than', 'them', 'they', 'these', 'those', 'only', 'just', 'because', 'while',
            'after', 'before', 'under', 'over', 'very', 'more', 'most', 'lesson', 'stage',
        ];

        $counts = [];
        foreach ($tokens as $token) {
            if (strlen($token) < 5 || in_array($token, $stop, true)) {
                continue;
            }
            $counts[$token] = ($counts[$token] ?? 0) + 1;
        }

        arsort($counts);
        return array_keys(array_slice($counts, 0, 8));
    }

    private function selectTeacherQuestion(Lesson $lesson, string $stage, User $user): ?LessonCheckpointQuestion
    {
        $questions = LessonCheckpointQuestion::query()
            ->where('lesson_id', $lesson->id)
            ->where('stage', $stage)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($questions->isEmpty()) {
            return null;
        }

        $lastQuestion = AiChatMessage::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->where('stage', $stage)
            ->where('question', '__checkpoint_start__')
            ->latest('id')
            ->value('answer');

        $eligible = $questions;
        if ($lastQuestion && $questions->count() > 1) {
            $eligible = $questions->filter(fn (LessonCheckpointQuestion $question) => $question->question_text !== $lastQuestion)->values();
            if ($eligible->isEmpty()) {
                $eligible = $questions;
            }
        }

        return $eligible->shuffle()->first();
    }

    private function retrieveCheckpointContext(Lesson $lesson, string $stage, string $query): string
    {
        $paths = LessonCheckpointCorpus::query()
            ->where('lesson_id', $lesson->id)
            ->where('stage', $stage)
            ->where('processing_status', 'completed')
            ->whereNotNull('vector_store_path')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('vector_store_path')
            ->all();

        if ($paths === []) {
            return '';
        }

        return $this->ragService->retrieveContextFromVectorStoresSafe($paths, $query, 2, 4);
    }

    private function normalizeSocraticReply(string $classification, float $confidence, string $feedback, ?string $followUp): array
    {
        $feedback = $this->limitWords($feedback, $followUp ? 14 : 30);
        $followUp = $followUp ? $this->limitWords($followUp, 12) : null;

        if ($feedback === '') {
            $feedback = $classification === 'correct' ? 'Good link to the key idea.' : 'Keep refining your answer.';
        }

        if ($followUp !== null && str_word_count(trim($feedback . ' ' . $followUp)) > 30) {
            $followUp = $this->limitWords($followUp, max(1, 30 - str_word_count($feedback)));
        }

        return [$classification, $confidence, $feedback, $followUp];
    }

    private function limitWords(string $text, int $maxWords): string
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        if (count($words) <= $maxWords) {
            return trim($text);
        }

        return trim(implode(' ', array_slice($words, 0, $maxWords)));
    }

    /**
     * Generate the full correct answer for a partial response in the explore stage.
     * This helps students see the complete understanding after a partial answer.
     */
    private function generateFullAnswer(Lesson $lesson, string $stage, string $userName): ?string
    {
        try {
            $context = $this->retrieveCheckpointContext($lesson, $stage, 'complete answer');
            
            if ($context === '' && !$this->ragService->isReady($lesson->id)) {
                return null;
            }

            $stageContent = optional($lesson->getStageContent($stage))->content;
            $contextText = trim(strip_tags((string) $stageContent));

            if ($context === '' && $contextText === '') {
                return null;
            }

            $fullContext = $context !== '' ? $context : $contextText;

            $system = 'You are an educational AI providing a clear, concise explanation. ' .
                'Generate a complete, well-formed answer (50-100 words) that directly answers the checkpoint question. ' .
                'Return only the answer text—no preamble, no numbering.';

            $prompt = "Stage: {$stage}\n" .
                "Goal: Provide a complete, clear explanation for the student.\n\n" .
                "Lesson context:\n<CONTEXT>\n{$fullContext}\n</CONTEXT>\n\n" .
                "Generate a complete answer to the checkpoint question that incorporates the key concepts from the lesson. " .
                "Keep it educational and age-appropriate. Address {$userName} naturally.";

            $fullAnswer = trim($this->ragService->callLlm($prompt, $system, 120, [
                'caller' => 'stage_checkpoint_full_answer',
                'lesson_id' => $lesson->id,
                'stage' => $stage,
            ]));

            return $fullAnswer !== '' ? $fullAnswer : null;
        } catch (\Throwable $e) {
            Log::warning('[StageCheckpoint] Full answer generation failed', [
                'lesson_id' => $lesson->id,
                'stage' => $stage,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Detect if a student's answer is too similar to the checkpoint question.
     * This catches cases where students paste back the question instead of answering.
     */
    private function isSimilarToQuestion(string $answer, string $question): bool
    {
        $answerLower = Str::lower($answer);
        $questionLower = Str::lower($question);

        // Direct match or near-exact
        if ($answerLower === $questionLower) {
            return true;
        }

        // Check if answer contains most of the question (within 80% match)
        $answerWords = preg_split('/\s+/', trim($answerLower)) ?: [];
        $questionWords = preg_split('/\s+/', trim($questionLower)) ?: [];

        if (count($questionWords) < 3) {
            return false;
        }

        $matchCount = 0;
        foreach ($questionWords as $qWord) {
            if (strlen($qWord) > 3 && in_array($qWord, $answerWords, true)) {
                $matchCount++;
            }
        }

        $matchRatio = $matchCount / count(array_filter($questionWords, fn ($w) => strlen($w) > 3));

        return $matchRatio >= 0.75;
    }
}
