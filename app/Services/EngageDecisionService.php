<?php

namespace App\Services;

use App\Models\AiChatMessage;
use App\Models\Lesson;
use App\Models\LessonMisconception;
use App\Models\MisconceptionEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EngageDecisionService
{
    private const MAX_FOLLOWUPS = 2;

    public function __construct(private readonly RagQueryService $ragService)
    {
    }

    public function assessAnswer(Lesson $lesson, User $user, string $answer): array
    {
        $answer = trim($answer);
        $stageContent = optional($lesson->getStageContent('engage'))->content;
        $contextText = trim(strip_tags((string) $stageContent));

        $history = AiChatMessage::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->where('stage', 'engage')
            ->latest('id')
            ->take(3)
            ->get();

        $turnIndex = (int) $history->count() + 1;

        $templateMatch = $this->detectTemplateMisconception($lesson->id, $answer);
        $ragDecision = null;
        $usedVectorRetrieval = false;

        if ($templateMatch !== null) {
            [$classification, $confidence] = ['misconception', 0.82];
        } else {
            $ragDecision = $this->classifyWithRag($lesson, $answer);

            if ($ragDecision !== null) {
                $classification = $ragDecision['classification'];
                $confidence = $ragDecision['confidence'];
                $usedVectorRetrieval = true;
            } else {
                [$classification, $confidence] = $this->classify($answer, $contextText, false);
            }
        }

        $followupsUsed = (int) $history->filter(fn (AiChatMessage $row) => !empty($row->follow_up_question))->count();
        $needsFollowup = in_array($classification, ['partial', 'misconception', 'off_topic'], true);

        $followUpQuestion = null;
        $engageStatus = 'in_progress';
        $completionReason = null;

        if ($classification === 'correct') {
            $engageStatus = 'complete';
            $completionReason = 'direct_correct_response';
        } elseif ($needsFollowup && $followupsUsed >= self::MAX_FOLLOWUPS) {
            $engageStatus = 'review_needed';
            $completionReason = 'max_followups_reached';
        } elseif ($needsFollowup) {
            $followUpQuestion = $ragDecision['follow_up']
                ?? $this->buildFollowupQuestion($classification, $contextText, $answer);
        }

        [$misconceptionId, $misconceptionSource] = $this->persistMisconception(
            $lesson,
            $user,
            $classification,
            $confidence,
            $answer,
            $templateMatch
        );

        $feedback = $ragDecision['feedback']
            ?? $this->buildFeedback($classification, $contextText, $followUpQuestion);

        return [
            'classification' => $classification,
            'confidence' => $confidence,
            'feedback_text' => $feedback,
            'follow_up_question' => $followUpQuestion,
            'misconception_id' => $misconceptionId,
            'misconception_source' => $misconceptionSource,
            'engage_status' => $engageStatus,
            'completion_reason' => $completionReason,
            'turn_index' => $turnIndex,
            'context_source' => $usedVectorRetrieval
                ? 'rag'
                : ($contextText !== '' ? 'stage_text' : 'none'),
            'retrieval_mode' => $usedVectorRetrieval
                ? 'vector'
                : ($contextText !== '' ? 'non_vector' : 'none'),
            'answer' => $followUpQuestion
                ? $feedback . ' Follow-up: ' . $followUpQuestion
                : $feedback,
        ];
    }

    private function classifyWithRag(Lesson $lesson, string $answer): ?array
    {
        if (str_word_count($answer) < 4) {
            return null;
        }

        if (!$this->ragService->isReady($lesson->id) || !$this->ragService->isOllamaHealthy()) {
            Log::warning('[EngageDecision] RAG service not ready or Ollama unhealthy, skipping RAG classification', [
                'lesson_id' => $lesson->id,
                'RAG Status' => $this->ragService->isOllamaHealthy(),
            ]);
            return null;
        }

        try {
            $context = $this->ragService->retrieveContextSafe($lesson, $answer, 4);
            if ($context === '') {
                return null;
            }

            $system = 'You are an educational assistant for engage-stage formative assessment. ' .
                'Return only valid JSON. No markdown, no explanation.';

            $prompt = "Classify the student answer using the lesson context.\n\n" .
                "Allowed classification values: correct, partial, misconception, off_topic.\n" .
                "Confidence must be a number between 0 and 1.\n" .
                "Feedback should be one concise sentence.\n" .
                "Follow-up should be null when classification is correct; otherwise ask one short coaching question.\n\n" .
                "Return exactly this JSON shape:\n" .
                "{\"classification\":\"correct|partial|misconception|off_topic\",\"confidence\":0.0,\"feedback\":\"...\",\"follow_up\":\"... or null\"}\n\n" .
                "Lesson context:\n<CONTEXT>\n{$context}\n</CONTEXT>\n\n" .
                "Student answer: {$answer}";

            $raw = trim($this->ragService->callLlm($prompt, $system, 200));
            $decoded = $this->decodeRagJson($raw);

            if (!is_array($decoded)) {
                return null;
            }

            $classification = (string) ($decoded['classification'] ?? '');
            $valid = ['correct', 'partial', 'misconception', 'off_topic'];

            if (!in_array($classification, $valid, true)) {
                return null;
            }

            $confidence = (float) ($decoded['confidence'] ?? 0.5);
            $confidence = max(0.0, min(1.0, $confidence));

            $feedback = trim((string) ($decoded['feedback'] ?? ''));
            if ($feedback === '') {
                return null;
            }

            $followUp = $decoded['follow_up'] ?? null;
            if (is_string($followUp)) {
                $followUp = trim($followUp);
                if ($followUp === '') {
                    $followUp = null;
                }
            } else {
                $followUp = null;
            }

            return [
                'classification' => $classification,
                'confidence' => $confidence,
                'feedback' => $feedback,
                'follow_up' => $followUp,
            ];
        } catch (\Throwable $e) {
            Log::warning('[EngageDecision] RAG classification failed, using fallback rules', [
                'lesson_id' => $lesson->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function decodeRagJson(string $raw): ?array
    {
        $clean = trim($raw);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;

        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $candidate = substr($clean, $start, $end - $start + 1);
        $decoded = json_decode($candidate, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function generateStartQuestion(Lesson $lesson): array
    {
        $stageContent = optional($lesson->getStageContent('engage'))->content;
        $contextText = trim(strip_tags((string) $stageContent));
        

        $topic = $this->deriveTopic($contextText);

        if ($contextText === '') {
            $question = 'What do you already know about this topic from real life?';
            return [
                'answer' => $question,
                'feedback_text' => 'Let us activate your prior knowledge first.',
                'follow_up_question' => $question,
                'engage_status' => 'in_progress',
                'context_source' => 'none',
                'retrieval_mode' => 'none',
            ];
        }

        $snippet = Str::limit($contextText, 50, '...');
        if (str_contains($stageContent, '?')) {
            $clean = preg_replace('/<p[^>]*>/', '', $stageContent);     // remove <p>
            $clean = preg_replace('/<\/p>/', "\n\n", $clean);      // </p> → newline
            $clean = preg_replace('/<br[^>]*>/', "\n", $clean);    // <br> → newline
            $clean = strip_tags($clean); // remove any remaining HTML
            $clean = preg_replace('/[.:]/', '', $clean); // remove colons and periods
            $lines = array_filter(array_map('trim', explode("\n", $clean)));
            $question = end($lines) ?: "Based on this scenario: {$snippet} What do you already know about {$topic}?";
        } else {
            $question = "Based on this scenario: {$snippet} What do you already know about {$topic}?";
        }

        return [
            'answer' => $question,
            'feedback_text' => 'Start with what you already know before we build new ideas.',
            'follow_up_question' => $question,
            'engage_status' => 'in_progress',
            'context_source' => 'stage_text',
            'retrieval_mode' => 'non_vector',
        ];
    }

    private function classify(string $answer, string $contextText, bool $hasTemplateMatch): array
    {
        if (str_word_count($answer) < 4) {
            return ['off_topic', 0.30];
        }

        if ($hasTemplateMatch) {
            return ['misconception', 0.82];
        }

        $keywords = $this->extractKeywords($contextText);
        if (count($keywords) === 0) {
            return ['partial', 0.50];
        }

        $answerText = Str::lower($answer);
        $hits = 0;
        foreach ($keywords as $keyword) {
            if (str_contains($answerText, $keyword)) {
                $hits++;
            }
        }

        $ratio = $hits / max(count($keywords), 1);

        if ($ratio >= 0.30) {
            return ['correct', 0.78];
        }

        if ($ratio >= 0.12) {
            return ['partial', 0.61];
        }

        return ['off_topic', 0.40];
    }

    private function detectTemplateMisconception(int $lessonId, string $answer): ?LessonMisconception
    {
        $templates = LessonMisconception::query()
            ->where('lesson_id', $lessonId)
            ->where('stage', 'engage')
            ->where('source', 'template')
            ->where('status', 'approved')
            ->get();

        $answerText = Str::lower($answer);

        foreach ($templates as $template) {
            $keywords = $this->extractKeywords(($template->label ?? '') . ' ' . ($template->description ?? ''));
            $matches = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($answerText, $keyword)) {
                    $matches++;
                }
            }

            if ($matches >= 2) {
                return $template;
            }
        }

        return null;
    }

    private function persistMisconception(
        Lesson $lesson,
        User $user,
        string $classification,
        float $confidence,
        string $answer,
        ?LessonMisconception $templateMatch
    ): array {
        if ($classification !== 'misconception') {
            return [null, 'none'];
        }

        $misconception = $templateMatch;
        $source = 'template';
        $status = 'captured';

        if (!$misconception) {
            $source = 'ai_candidate';
            $status = 'queued_for_review';
            $label = 'AI candidate: ' . Str::limit($answer, 80, '...');

            $misconception = LessonMisconception::firstOrCreate(
                [
                    'lesson_id' => $lesson->id,
                    'stage' => 'engage',
                    'label' => $label,
                    'source' => 'ai_candidate',
                ],
                [
                    'description' => Str::limit($answer, 255, '...'),
                    'status' => 'pending_review',
                    'confidence' => $confidence,
                    'created_by' => $user->id,
                ]
            );
        }

        MisconceptionEvent::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'stage' => 'engage',
            'misconception_id' => $misconception?->id,
            'source' => $source,
            'student_answer' => $answer,
            'evidence_span' => Str::limit($answer, 120, '...'),
            'confidence' => $confidence,
            'status' => $status,
        ]);

        return [$misconception?->id, $source];
    }

    private function buildFollowupQuestion(string $classification, string $contextText, string $answer): string
    {
        $topic = $this->deriveTopic($contextText);

        if ($classification === 'misconception') {
            return "Can you revisit your idea and explain how {$topic} works in the scenario evidence?";
        }

        if ($classification === 'partial') {
            return "Good start. What key detail about {$topic} is still missing in your explanation?";
        }

        return "Let us refocus: which part of the scenario best shows {$topic}?";
    }

    private function buildFeedback(string $classification, string $contextText, ?string $followUpQuestion): string
    {
        return match ($classification) {
            'correct' => 'Your response aligns with the scenario. Great prior knowledge activation.',
            'partial' => 'Your response is partly correct, but one key concept is missing.',
            'misconception' => 'I noticed a misconception in your response. Let us correct it before moving on.',
            default => 'Your response seems off-topic for this scenario.',
        };
    }

    private function deriveTopic(string $contextText): string
    {
        $keywords = $this->extractKeywords($contextText);
        return $keywords[0] ?? 'the main concept';
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

        return array_slice(array_keys($counts), 0, 10);
    }
}
