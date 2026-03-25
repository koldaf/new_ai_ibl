<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    public function submit(Request $request, Lesson $lesson)
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'required|in:a,b,c,d',
        ]);

        $questions = $lesson->quizQuestions()->get()->keyBy('id');

        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No quiz questions available for this lesson.',
            ], 404);
        }

        $correctCount = 0;
        $totalQuestions = $questions->count();

        foreach ($request->answers as $questionId => $selected) {
            $question = $questions->get($questionId);

            if (!$question instanceof QuizQuestion) {
                continue;
            }

            $selected = strtolower($selected);
            $isCorrect = $selected === $question->correct_option;

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

            if ($isCorrect) {
                $correctCount++;
            }
        }

        $progress = LessonProgress::where('user_id', Auth::id())
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($progress && !$progress->evaluate_completed) {
            $progress->evaluate_completed = true;

            $allCompleted = $progress->engage_completed
                && $progress->explore_completed
                && $progress->explain_completed
                && $progress->elaborate_completed
                && $progress->evaluate_completed;

            if ($allCompleted && !$progress->completed) {
                $progress->completed = true;
                $progress->completed_at = now();
            }

            $progress->save();
        }

        $percentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100) : 0;

        return response()->json([
            'success' => true,
            'score' => $correctCount,
            'total' => $totalQuestions,
            'percentage' => $percentage,
        ]);
    }
}
