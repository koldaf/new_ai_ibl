<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\AiChatMessage;
use App\Services\EngageDecisionService;
use App\Services\RagQueryService;
use Illuminate\Http\Request;

class AIChatController extends Controller
{
    protected RagQueryService $ragQueryService;
    protected EngageDecisionService $engageDecisionService;

    public function __construct(RagQueryService $ragQueryService, EngageDecisionService $engageDecisionService)
    {
        $this->ragQueryService = $ragQueryService;
        $this->engageDecisionService = $engageDecisionService;
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

        try {
            if ($stage === 'engage') {
                if (($lesson->getStageContent('engage')?->activity_mode ?? 'chat') !== 'chat') {
                    return response()->json([
                        'success' => false,
                        'message' => 'This lesson uses MCQ mode for Engage. Use the checkpoint instead of chat.',
                    ], 422);
                }

                $payload = $intent === 'start'
                    ? $this->engageDecisionService->generateStartQuestion($lesson)
                    : $this->engageDecisionService->assessAnswer($lesson, $request->user(), $request->question);

                $parentMessage = AiChatMessage::query()
                    ->where('user_id', $request->user()->id)
                    ->where('lesson_id', $lesson->id)
                    ->where('stage', 'engage')
                    ->latest('id')
                    ->first();

                $chat = AiChatMessage::create([
                    'user_id' => $request->user()->id,
                    'lesson_id' => $lesson->id,
                    'stage' => 'engage',
                    'question' => $request->question,
                    'answer' => $payload['answer'] ?? '',
                    'classification' => $payload['classification'] ?? null,
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

                return response()->json([
                    'success' => true,
                    'stage' => 'engage',
                    'intent' => $intent,
                    'answer' => $chat->answer,
                    'classification' => $chat->classification,
                    'confidence' => $chat->confidence,
                    'engage_status' => $chat->engage_status,
                    'follow_up_question' => $chat->follow_up_question,
                    'misconception_source' => $chat->misconception_source,
                ]);
            }

            $answer = $this->ragQueryService->generateResponse(
                $request->question,
                $lesson->id,
                $stage
            );

            AIChatMessage::create([
                'user_id' => $request->user()->id,
                'lesson_id' => $lesson->id,
                'stage' => $stage,
                'question' => $request->question,
                'answer' => $answer,
                'context_source' => 'rag',
                'retrieval_mode' => 'vector',
            ]);
                        
            return response()->json([
                'success' => true,
                'stage' => $stage,
                'answer' => $answer,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI service error: ' . $e->getMessage(),
            ], 500);
        }
    }
}