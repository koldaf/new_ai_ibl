<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\AiChatMessage;
use App\Models\EngageMcqAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LessonController extends Controller
{
    public function index()
    {
        $lessons = Lesson::with(['progress' => function ($q) {
            $q->where('user_id', Auth::id());
        }])->paginate(10);

        return view('dashboard.student.lessons.index', compact('lessons'));
    }

    public function show(Lesson $lesson)
    {
        // Get or create progress for this user and lesson
        $progress = LessonProgress::firstOrCreate([
            'user_id' => Auth::id(),
            'lesson_id' => $lesson->id,
        ]);

        // Load stage contents and media for each stage
        $stages = ['engage', 'explore', 'explain', 'elaborate', 'evaluate'];
        $stageData = [];
        foreach ($stages as $stage) {
            $stageData[$stage] = [
                'content' => $lesson->getStageContent($stage),
                'media'   => $lesson->getStageMedia($stage),
            ];
        }

        $engageMessages = AiChatMessage::query()
            ->where('user_id', Auth::id())
            ->where('lesson_id', $lesson->id)
            ->where('stage', 'engage')
            ->orderBy('id')
            ->get();

        $latestEngageMessage = $engageMessages->last();
        $engageMode = $lesson->getStageContent('engage')?->activity_mode ?? 'chat';
        $engageMcqQuestion = $lesson->getEngageMcqQuestion('engage');
        $engageMcqAttempt = EngageMcqAttempt::query()
            ->where('user_id', Auth::id())
            ->where('lesson_id', $lesson->id)
            ->when($engageMcqQuestion, fn ($query) => $query->where('engage_mcq_question_id', $engageMcqQuestion->id))
            ->latest('id')
            ->first();
        $canMarkEngageComplete = $engageMode === 'mcq'
            ? (bool) $engageMcqAttempt
            : $latestEngageMessage?->engage_status === 'complete';

        // Load quiz questions for evaluate stage
        $quizQuestions = $lesson->quizQuestions()->get();

        // Get user's previous quiz attempts for this lesson
        $previousAttempts = QuizAttempt::where('user_id', Auth::id())
            ->where('lesson_id', $lesson->id)
            ->get()
            ->keyBy('question_id');

        return view('dashboard.student.lessons.show', compact(
            'lesson',
            'progress',
            'stageData',
            'stages',
            'quizQuestions',
            'previousAttempts',
            'engageMessages',
            'canMarkEngageComplete',
            'engageMode',
            'engageMcqQuestion',
            'engageMcqAttempt'
        ));
    }

    public function completeStage(Request $request, Lesson $lesson, string $stage)
    {
        return $this->markStageComplete($request, $lesson, $stage);
    }

    public function markStageComplete(Request $request, Lesson $lesson, $stage)
    {
        $validStages = ['engage', 'explore', 'explain', 'elaborate', 'evaluate'];
        if (!in_array($stage, $validStages)) {
            return response()->json(['error' => 'Invalid stage'], 422);
        }

        $progress = LessonProgress::where('user_id', Auth::id())
            ->where('lesson_id', $lesson->id)
            ->firstOrFail();

        if ($stage === 'engage') {
            $engageMode = $lesson->getStageContent('engage')?->activity_mode ?? 'chat';

            if ($engageMode === 'mcq') {
                $engageQuestion = $lesson->getEngageMcqQuestion('engage');
                $engageAttempt = EngageMcqAttempt::query()
                    ->where('user_id', Auth::id())
                    ->where('lesson_id', $lesson->id)
                    ->when($engageQuestion, fn ($query) => $query->where('engage_mcq_question_id', $engageQuestion->id))
                    ->latest('id')
                    ->first();

                if (!$engageAttempt) {
                    return response()->json([
                        'error' => 'Submit the Engage checkpoint before marking this stage as complete.',
                    ], 422);
                }
            } else {
                $latestEngageMessage = AiChatMessage::query()
                    ->where('user_id', Auth::id())
                    ->where('lesson_id', $lesson->id)
                    ->where('stage', 'engage')
                    ->latest('id')
                    ->first();

                if (!$latestEngageMessage || $latestEngageMessage->engage_status !== 'complete') {
                    return response()->json([
                        'error' => 'Complete the Engage discussion with AI before marking this stage as complete.',
                    ], 422);
                }
            }
        }

        $field = $stage . '_completed';
        $progress->$field = true;

        // Check if all stages are completed
        $allCompleted = $progress->engage_completed && $progress->explore_completed &&
                        $progress->explain_completed && $progress->elaborate_completed &&
                        $progress->evaluate_completed;
        if ($allCompleted && !$progress->completed) {
            $progress->completed = true;
            $progress->completed_at = now();
        }

        $progress->save();

        return response()->json(['success' => true, 'progress' => $progress]);
    }

    public function submitEngageMcq(Request $request, Lesson $lesson)
    {
        $engageMode = $lesson->getStageContent('engage')?->activity_mode ?? 'chat';
        if ($engageMode !== 'mcq') {
            return response()->json([
                'success' => false,
                'message' => 'This lesson is not using MCQ mode for Engage.',
            ], 422);
        }

        $question = $lesson->getEngageMcqQuestion('engage');
        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'No Engage checkpoint is configured for this lesson.',
            ], 404);
        }

        $validated = $request->validate([
            'selected_option' => 'required|in:a,b,c,d',
        ]);

        $selectedOption = strtolower($validated['selected_option']);
        $isCorrect = $selectedOption === $question->correct_option;
        $resolvedFeedback = $question->feedbackForOption($selectedOption);

        $attempt = EngageMcqAttempt::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'lesson_id' => $lesson->id,
                'engage_mcq_question_id' => $question->id,
            ],
            [
                'selected_option' => $selectedOption,
                'is_correct' => $isCorrect,
                'resolved_feedback' => $resolvedFeedback,
            ]
        );

        $progress = LessonProgress::firstOrCreate([
            'user_id' => Auth::id(),
            'lesson_id' => $lesson->id,
        ]);
        $progress->engage_completed = true;

        $allCompleted = $progress->engage_completed && $progress->explore_completed &&
            $progress->explain_completed && $progress->elaborate_completed &&
            $progress->evaluate_completed;
        if ($allCompleted && !$progress->completed) {
            $progress->completed = true;
            $progress->completed_at = now();
        }
        $progress->save();

        return response()->json([
            'success' => true,
            'message' => 'Engage checkpoint submitted.',
            'selected_option' => $attempt->selected_option,
            'is_correct' => $attempt->is_correct,
            'feedback' => $attempt->resolved_feedback,
        ]);
    }

    public function submitQuiz(Request $request, Lesson $lesson)
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'required|in:a,b,c,d',
        ]);

        $questions = $lesson->quizQuestions()->get()->keyBy('id');
        $correctCount = 0;
        $total = $questions->count();

        foreach ($request->answers as $questionId => $selected) {
            $question = $questions->get($questionId);
            if (!$question) continue;

            $isCorrect = ($selected === $question->correct_option);

            QuizAttempt::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'lesson_id' => $lesson->id,
                    'question_id' => $questionId,
                ],
                [
                    'selected_option' => $selected,
                    'is_correct' => $isCorrect,
                ]
            );

            if ($isCorrect) $correctCount++;
        }

        // Mark evaluate stage as completed
        $progress = LessonProgress::where('user_id', Auth::id())
            ->where('lesson_id', $lesson->id)
            ->first();
        if ($progress && !$progress->evaluate_completed) {
            $progress->evaluate_completed = true;
            // Re-check all completed
            $allCompleted = $progress->engage_completed && $progress->explore_completed &&
                            $progress->explain_completed && $progress->elaborate_completed &&
                            $progress->evaluate_completed;
            if ($allCompleted && !$progress->completed) {
                $progress->completed = true;
                $progress->completed_at = now();
            }
            $progress->save();
        }

        return response()->json([
            'success' => true,
            'score' => $correctCount,
            'total' => $total,
            'percentage' => $total > 0 ? round(($correctCount / $total) * 100) : 0,
        ]);
    }
}