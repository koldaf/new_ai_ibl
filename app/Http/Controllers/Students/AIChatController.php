<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\AiChatMessage;
use App\Services\RagQueryService;
use Illuminate\Http\Request;

class AIChatController extends Controller
{
    protected RagQueryService $ragQueryService;

    public function __construct(RagQueryService $ragQueryService)
    {
        $this->ragQueryService = $ragQueryService;
    }

    public function ask(Request $request, Lesson $lesson)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'stage' => 'nullable|string|in:engage,explore,explain,elaborate,evaluate',
        ]);

        $stage = $request->input('stage', 'engage');

        try {
            $answer = $this->ragQueryService->generateResponse(
                $request->question,
                $lesson->id,
                $stage
            );
            //save to database if needed, e.g. AIChatMessage::create([...]);
            AIChatMessage::create([
                'user_id' => $request->user()->id,
                'lesson_id' => $lesson->id,
                'stage' => $stage,
                'question' => $request->question,
                'answer' => $answer,
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