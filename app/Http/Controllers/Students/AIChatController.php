<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\AiChatMessage;
use App\Services\AiMemoryService;
use App\Services\BloomTaxonomyClassifier;
use App\Services\EngageDecisionService;
use App\Services\InquiryPhaseAnalyticsService;
use App\Services\RagQueryService;
use App\Services\StageCheckpointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AIChatController extends Controller
{
    protected RagQueryService $ragQueryService;
    protected EngageDecisionService $engageDecisionService;
    protected AiMemoryService $memoryService;
    protected StageCheckpointService $checkpointService;
    protected BloomTaxonomyClassifier $bloomClassifier;
    protected InquiryPhaseAnalyticsService $inquiryAnalytics;

    public function __construct(
        RagQueryService $ragQueryService,
        EngageDecisionService $engageDecisionService,
        AiMemoryService $memoryService,
        StageCheckpointService $checkpointService,
        BloomTaxonomyClassifier $bloomClassifier,
        InquiryPhaseAnalyticsService $inquiryAnalytics
    ) {
        $this->ragQueryService = $ragQueryService;
        $this->engageDecisionService = $engageDecisionService;
        $this->memoryService = $memoryService;
        $this->checkpointService = $checkpointService;
        $this->bloomClassifier = $bloomClassifier;
        $this->inquiryAnalytics = $inquiryAnalytics;
    }

    public function ask(Request $request, Lesson $lesson)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'stage' => 'nullable|string|in:engage,explore,explain,elaborate,evaluate',
            'intent' => 'nullable|string|in:start,answer,ask',
        ]);

        $stage = $request->input('stage', 'engage');
        $intent = $request->input('intent', 'ask');
        $user = $request->user();

        try {
            $this->inquiryAnalytics->touchStage($user, $lesson, $stage);

            if ($stage === 'engage') {
                if (($lesson->getStageContent('engage')?->activity_mode ?? 'chat') !== 'chat') {
                    return response()->json([
                        'success' => false,
                        'message' => 'This lesson uses MCQ mode for Engage. Use the checkpoint instead of chat.',
                    ], 422);
                }

                $payload = $intent === 'start'
                    ? $this->engageDecisionService->generateStartQuestion($lesson, $user)
                    : $this->engageDecisionService->assessAnswer($lesson, $user, $request->question);

                $bloom = $this->classifyLearnerQuestion($request->question, $stage, $intent);

                $parentMessage = AiChatMessage::query()
                    ->where('user_id', $user->id)
                    ->where('lesson_id', $lesson->id)
                    ->where('stage', 'engage')
                    ->latest('id')
                    ->first();

                $chat = AiChatMessage::create([
                    'user_id' => $user->id,
                    'lesson_id' => $lesson->id,
                    'stage' => 'engage',
                    'question' => $request->question,
                    'answer' => $payload['answer'] ?? '',
                    'classification' => $payload['classification'] ?? null,
                    'bloom_level' => $bloom['bloom_level'],
                    'bloom_confidence' => $bloom['bloom_confidence'],
                    'confidence' => $payload['confidence'] ?? null,
                    'feedback_text' => $payload['feedback_text'] ?? null,
                    'follow_up_question' => $payload['follow_up_question'] ?? null,
                    'misconception_source' => $payload['misconception_source'] ?? 'none',
                    'misconception_id' => $payload['misconception_id'] ?? null,
                    'engage_status' => $payload['engage_status'] ?? 'in_progress',
                    'completion_reason' => $payload['completion_reason'] ?? null,
                    'context_source' => $payload['context_source'] ?? null,
                    'retrieval_mode' => $payload['retrieval_mode'] ?? null,
                    'turn_index' => $payload['turn_index'] ?? null,
                    'parent_message_id' => $parentMessage?->id,
                ]);

                if ($intent !== 'start' && filled($request->question)) {
                    $evidenceCount = $this->inquiryAnalytics->deriveEvidenceCountFromResponse((string) ($payload['answer'] ?? ''));
                    $this->inquiryAnalytics->recordQuestion($user, $lesson, $stage, $evidenceCount);
                }

                return response()->json([
                    'success' => true,
                    'stage' => 'engage',
                    'intent' => $intent,
                    'answer' => $chat->answer,
                    'classification' => $chat->classification,
                    'bloom_level' => $chat->bloom_level,
                    'bloom_confidence' => $chat->bloom_confidence,
                    'confidence' => $chat->confidence,
                    'engage_status' => $chat->engage_status,
                    'follow_up_question' => $chat->follow_up_question,
                    'misconception_source' => $chat->misconception_source,
                ]);
            }

            $memoryHistory = $this->memoryService->getHistoryForPrompt($user, $lesson);
            $memoryContext = $this->memoryService->buildPromptContext($memoryHistory, $lesson->id);

            // Checkpoint flow for explore / explain / elaborate
            if ($this->checkpointService->isCheckpointStage($stage) && in_array($intent, ['start', 'answer'], true)) {
                return $this->handleCheckpoint($request, $lesson, $stage, $intent);
            }

            $answer = $this->ragQueryService->generateResponse(
                $request->question,
                $lesson->id,
                $stage,
                $user->name,
                3,
                $memoryContext,
                $this->memoryService->isEnabled()
            );

            $bloom = $this->classifyLearnerQuestion($request->question, $stage, $intent);

            AiChatMessage::create([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'stage' => $stage,
                'question' => $request->question,
                'answer' => $answer,
                'bloom_level' => $bloom['bloom_level'],
                'bloom_confidence' => $bloom['bloom_confidence'],
                'context_source' => 'rag',
                'retrieval_mode' => 'vector',
            ]);

            if (in_array($intent, ['ask', 'answer'], true) && filled($request->question)) {
                $evidenceCount = $this->inquiryAnalytics->deriveEvidenceCountFromResponse($answer);
                $this->inquiryAnalytics->recordQuestion($user, $lesson, $stage, $evidenceCount);
            }

            return response()->json([
                'success' => true,
                'stage' => $stage,
                'answer' => $answer,
                'bloom_level' => $bloom['bloom_level'],
                'bloom_confidence' => $bloom['bloom_confidence'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI service error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Streaming twin of ask() for the plain, open-ended student Q&A path only
     * (explore/explain/elaborate/evaluate "ask" intent) — engage classification
     * and checkpoint start/answer stay on the non-streaming ask() endpoint since
     * they return structured JSON, not free text meant to be typed out live.
     */
    public function askStream(Request $request, Lesson $lesson)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'stage' => 'required|string|in:explore,explain,elaborate,evaluate',
        ]);

        $stage = $request->input('stage');
        $question = $request->question;
        $user = $request->user();

        $this->inquiryAnalytics->touchStage($user, $lesson, $stage);

        $memoryHistory = $this->memoryService->getHistoryForPrompt($user, $lesson);
        $memoryContext = $this->memoryService->buildPromptContext($memoryHistory, $lesson->id);
        $memoryEnabled = $this->memoryService->isEnabled();

        return response()->stream(function () use ($lesson, $question, $stage, $user, $memoryContext, $memoryEnabled) {
            $writeLine = function (array $payload) {
                echo json_encode($payload) . "\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $fullAnswer = '';

            try {
                $generator = $this->ragQueryService->generateResponseStream(
                    $question,
                    $lesson->id,
                    $stage,
                    $user->name,
                    3,
                    $memoryContext,
                    $memoryEnabled
                );

                foreach ($generator as $token) {
                    $writeLine(['token' => $token, 'done' => false]);
                }

                $fullAnswer = (string) $generator->getReturn();
            } catch (\Throwable $e) {
                Log::error('[AIChat] Streaming ask failed', [
                    'error' => $e->getMessage(),
                    'lesson_id' => $lesson->id,
                ]);

                $fullAnswer = 'Sorry, something went wrong generating a response. Please try again.';
                $writeLine(['token' => $fullAnswer, 'done' => false]);
            }

            $bloom = $this->classifyLearnerQuestion($question, $stage, 'ask');

            AiChatMessage::create([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'stage' => $stage,
                'question' => $question,
                'answer' => $fullAnswer,
                'bloom_level' => $bloom['bloom_level'],
                'bloom_confidence' => $bloom['bloom_confidence'],
                'context_source' => 'rag',
                'retrieval_mode' => 'vector',
            ]);

            $evidenceCount = $this->inquiryAnalytics->deriveEvidenceCountFromResponse($fullAnswer);
            $this->inquiryAnalytics->recordQuestion($user, $lesson, $stage, $evidenceCount);

            $writeLine([
                'token' => '',
                'done' => true,
                'bloom_level' => $bloom['bloom_level'],
                'bloom_confidence' => $bloom['bloom_confidence'],
            ]);
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function handleCheckpoint(Request $request, Lesson $lesson, string $stage, string $intent)
    {
        $user = $request->user();

        $latestMessage = AiChatMessage::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->where('stage', $stage)
            ->latest('id')
            ->first();

        if ($intent === 'start') {
            $payload = $this->checkpointService->generateCheckpointQuestion($lesson, $stage, $user);

            $chat = AiChatMessage::create([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'stage' => $stage,
                'question' => '__checkpoint_start__',
                'answer' => $payload['answer'],
                'engage_status' => $payload['engage_status'],
                'context_source' => $payload['context_source'],
                'retrieval_mode' => $payload['retrieval_mode'],
                'turn_index' => 1,
                'parent_message_id' => $latestMessage?->id,
            ]);

            return response()->json([
                'success' => true,
                'stage' => $stage,
                'intent' => 'start',
                'answer' => $chat->answer,
                'engage_status' => $chat->engage_status,
            ]);
        }

        // intent === 'answer'
        $payload = $this->checkpointService->evaluateCheckpointAnswer($lesson, $user, $request->question, $stage);
        $bloom = $this->classifyLearnerQuestion($request->question, $stage, $intent);

        $chat = AiChatMessage::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'stage' => $stage,
            'question' => $request->question,
            'answer' => $payload['answer'],
            'classification' => $payload['classification'],
            'bloom_level' => $bloom['bloom_level'],
            'bloom_confidence' => $bloom['bloom_confidence'],
            'confidence' => $payload['confidence'],
            'feedback_text' => $payload['feedback_text'],
            'follow_up_question' => $payload['follow_up_question'],
            'engage_status' => $payload['engage_status'],
            'completion_reason' => $payload['completion_reason'],
            'turn_index' => $payload['turn_index'],
            'context_source' => $payload['context_source'],
            'retrieval_mode' => $payload['retrieval_mode'],
            'parent_message_id' => $latestMessage?->id,
        ]);

        $evidenceCount = $this->inquiryAnalytics->deriveEvidenceCountFromResponse((string) ($payload['answer'] ?? ''));
        $this->inquiryAnalytics->recordQuestion($user, $lesson, $stage, $evidenceCount);

        return response()->json([
            'success' => true,
            'stage' => $stage,
            'intent' => 'answer',
            'answer' => $chat->answer,
            'classification' => $chat->classification,
            'bloom_level' => $chat->bloom_level,
            'bloom_confidence' => $chat->bloom_confidence,
            'confidence' => $chat->confidence,
            'engage_status' => $chat->engage_status,
            'follow_up_question' => $chat->follow_up_question,
            'full_answer' => $payload['full_answer'] ?? null,
        ]);
    }

    private function classifyLearnerQuestion(string $question, string $stage, string $intent): array
    {
        if ($intent === 'start') {
            return ['bloom_level' => null, 'bloom_confidence' => null];
        }

        return $this->bloomClassifier->classify($question, $stage);
    }
}