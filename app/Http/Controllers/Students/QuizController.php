<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    public function submit(Request $request, Lesson $lesson)
    {
        $questions = $lesson->quizQuestions;
        
        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No quiz questions available for this lesson',
            ], 404);
        }

        $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'required|string|in:A,B,C,D',
        ]);

        $answers = $request->answers;
        $score = 0;
        $totalQuestions = $questions->count();

        foreach ($questions as $question) {
            $userAnswer = $answers[$question->id] ?? null;
            if ($userAnswer && $userAnswer === $question->correct_option) {
                $score++;
            }
        }

        $percentage = ($score / $totalQuestions) * 100;

        // Save quiz attempt
        QuizAttempt::create([
            'user_id' => Auth::id(),
            'lesson_id' => $lesson->id,
            'score' => $score,
            'total_questions' => $totalQuestions,
            'percentage' => $percentage,
            'answers' => json_encode($answers),
        ]);

        return response()->json([
            'success' => true,
            'score' => $score,
            'total' => $totalQuestions,
            'percentage' => $percentage,
            'passed' => $percentage >= 70, // 70% passing grade
        ]);
    }
}
