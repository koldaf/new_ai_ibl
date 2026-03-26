<?php

namespace App\Http\Controllers\Teachers;

use App\Http\Controllers\Controller;
use App\Models\AiChatMessage;
use App\Models\EngageMcqAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $teacher = Auth::user();
        $filterBaseQuery = Lesson::query()->where('teacher_id', $teacher->id);

        $subjects = (clone $filterBaseQuery)
            ->whereNotNull('subject')
            ->distinct()
            ->orderBy('subject')
            ->pluck('subject')
            ->filter()
            ->values();

        $gradeLevels = (clone $filterBaseQuery)
            ->whereNotNull('grade_level')
            ->distinct()
            ->orderBy('grade_level')
            ->pluck('grade_level')
            ->filter()
            ->values();

        $lessonsQuery = Lesson::query()
            ->withCount('progress')
            ->with(['progress' => function ($query) {
                $query->with('user');
            }])
            ->where('teacher_id', $teacher->id);

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $lessonsQuery->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('subject')) {
            $lessonsQuery->where('subject', $request->string('subject')->toString());
        }

        if ($request->filled('grade_level')) {
            $lessonsQuery->where('grade_level', $request->string('grade_level')->toString());
        }

        $lessons = $lessonsQuery
            ->orderBy('title')
            ->get();

        $lessonSummaries = $lessons->map(function (Lesson $lesson) {
            $progress = $lesson->progress;
            $studentCount = $progress->count();
            $completedLessons = $progress->where('completed', true)->count();
            $exploreCompleted = $progress->where('explore_completed', true)->count();
            $totalExploreActivities = $lesson->countStageActivities('explore');

            $stageRates = [
                'engage' => $studentCount > 0 ? round(($progress->where('engage_completed', true)->count() / $studentCount) * 100) : 0,
                'explore' => $studentCount > 0 ? round(($progress->where('explore_completed', true)->count() / $studentCount) * 100) : 0,
                'explain' => $studentCount > 0 ? round(($progress->where('explain_completed', true)->count() / $studentCount) * 100) : 0,
                'elaborate' => $studentCount > 0 ? round(($progress->where('elaborate_completed', true)->count() / $studentCount) * 100) : 0,
                'evaluate' => $studentCount > 0 ? round(($progress->where('evaluate_completed', true)->count() / $studentCount) * 100) : 0,
            ];

            $overallStageAverage = collect($stageRates)->avg();

            return [
                'lesson' => $lesson,
                'student_count' => $studentCount,
                'completed_lessons' => $completedLessons,
                'explore_completed' => $exploreCompleted,
                'explore_completion_rate' => $studentCount > 0 ? round(($exploreCompleted / $studentCount) * 100) : 0,
                'total_explore_activities' => $totalExploreActivities,
                'completion_rate' => $studentCount > 0 ? round(($completedLessons / $studentCount) * 100) : 0,
                'stage_rates' => $stageRates,
                'overall_stage_average' => round($overallStageAverage),
            ];
        });

        $overviewStats = [
            'lesson_count' => $lessonSummaries->count(),
            'tracked_learners' => $lessonSummaries->sum('student_count'),
            'avg_explore_completion' => $lessonSummaries->count() > 0 ? round($lessonSummaries->avg('explore_completion_rate')) : 0,
            'avg_overall_completion' => $lessonSummaries->count() > 0 ? round($lessonSummaries->avg('completion_rate')) : 0,
        ];

        return view('dashboard.teacher.index', [
            'teacher' => $teacher,
            'lessonSummaries' => $lessonSummaries,
            'overviewStats' => $overviewStats,
            'subjects' => $subjects,
            'gradeLevels' => $gradeLevels,
            'filters' => [
                'q' => (string) $request->input('q', ''),
                'subject' => (string) $request->input('subject', ''),
                'grade_level' => (string) $request->input('grade_level', ''),
            ],
        ]);
    }

    public function showLesson(Request $request, Lesson $lesson)
    {
        abort_unless((int) $lesson->teacher_id === (int) Auth::id(), 403);

        $exploreActivities = $lesson->getStageActivities('explore');

        $progressQuery = LessonProgress::query()
            ->with('user')
            ->where('lesson_id', $lesson->id);

        if ($request->filled('learner')) {
            $learner = trim((string) $request->input('learner'));
            $progressQuery->whereHas('user', function ($query) use ($learner) {
                $query->where('name', 'like', '%' . $learner . '%');
            });
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'completed') {
                $progressQuery->where('completed', true);
            } elseif ($status === 'in_progress') {
                $progressQuery->where('completed', false)
                    ->where(function ($query) {
                        $query->where('engage_completed', true)
                            ->orWhere('explore_completed', true)
                            ->orWhere('explain_completed', true)
                            ->orWhere('elaborate_completed', true)
                            ->orWhere('evaluate_completed', true);
                    });
            } elseif ($status === 'not_started') {
                $progressQuery->where('completed', false)
                    ->where('engage_completed', false)
                    ->where('explore_completed', false)
                    ->where('explain_completed', false)
                    ->where('elaborate_completed', false)
                    ->where('evaluate_completed', false);
            }
        }

        $sort = $request->string('sort')->toString();
        if ($sort === 'name_asc') {
            $progressQuery
                ->join('users', 'lesson_progress.user_id', '=', 'users.id')
                ->select('lesson_progress.*')
                ->orderBy('users.name')
                ->orderByDesc('lesson_progress.updated_at');
        } elseif ($sort === 'progress_desc') {
            $progressQuery
                ->orderByRaw('(CASE WHEN engage_completed THEN 1 ELSE 0 END + CASE WHEN explore_completed THEN 1 ELSE 0 END + CASE WHEN explain_completed THEN 1 ELSE 0 END + CASE WHEN elaborate_completed THEN 1 ELSE 0 END + CASE WHEN evaluate_completed THEN 1 ELSE 0 END) DESC')
                ->orderByDesc('updated_at');
        } else {
            $progressQuery->orderByDesc('updated_at');
        }

        $allProgress = (clone $progressQuery)->get();
        $totalLearners = $allProgress->count();
        $stagePercentages = [
            'engage' => $totalLearners > 0 ? round(($allProgress->where('engage_completed', true)->count() / $totalLearners) * 100) : 0,
            'explore' => $totalLearners > 0 ? round(($allProgress->where('explore_completed', true)->count() / $totalLearners) * 100) : 0,
            'explain' => $totalLearners > 0 ? round(($allProgress->where('explain_completed', true)->count() / $totalLearners) * 100) : 0,
            'elaborate' => $totalLearners > 0 ? round(($allProgress->where('elaborate_completed', true)->count() / $totalLearners) * 100) : 0,
            'evaluate' => $totalLearners > 0 ? round(($allProgress->where('evaluate_completed', true)->count() / $totalLearners) * 100) : 0,
        ];

        $overallCompletionRate = $totalLearners > 0
            ? round(($allProgress->where('completed', true)->count() / $totalLearners) * 100)
            : 0;

        $paginatedProgress = $progressQuery->paginate(20)->withQueryString();
        $userIds = $paginatedProgress->getCollection()->pluck('user_id')->all();
        $exploreCompletionByUser = collect();

        if (!empty($userIds)) {
            $exploreCompletionByUser = \App\Models\LessonActivityCompletion::query()
                ->where('lesson_id', $lesson->id)
                ->where('stage', 'explore')
                ->whereIn('user_id', $userIds)
                ->selectRaw('user_id, count(*) as completed_count')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        }

        $studentRows = $paginatedProgress->getCollection()->map(function (LessonProgress $progress) use ($exploreActivities, $exploreCompletionByUser) {
            $completedStages = collect([
                $progress->engage_completed,
                $progress->explore_completed,
                $progress->explain_completed,
                $progress->elaborate_completed,
                $progress->evaluate_completed,
            ])->filter()->count();

            $overallProgress = round(($completedStages / 5) * 100);
            $exploreCount = (int) ($exploreCompletionByUser->get($progress->user_id)->completed_count ?? 0);

            return [
                'progress' => $progress,
                'user' => $progress->user,
                'overall_progress' => $overallProgress,
                'explore_completed_count' => $exploreCount,
                'explore_total_count' => $exploreActivities->count(),
            ];
        });

        $paginatedProgress->setCollection($studentRows);

        return view('dashboard.teacher.lesson', [
            'lesson' => $lesson,
            'exploreActivities' => $exploreActivities,
            'studentRows' => $paginatedProgress,
            'stagePercentages' => $stagePercentages,
            'overallCompletionRate' => $overallCompletionRate,
            'learnerCount' => $totalLearners,
            'filters' => [
                'learner' => (string) $request->input('learner', ''),
                'status' => (string) $request->input('status', ''),
                'sort' => (string) $request->input('sort', ''),
            ],
        ]);
    }

    public function exportLesson(Request $request, Lesson $lesson): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless((int) $lesson->teacher_id === (int) Auth::id(), 403);

        $exploreActivities = $lesson->getStageActivities('explore');
        $totalActivities = $exploreActivities->count();

        $progressQuery = LessonProgress::query()
            ->with('user')
            ->where('lesson_id', $lesson->id);

        if ($request->filled('learner')) {
            $learner = trim((string) $request->input('learner'));
            $progressQuery->whereHas('user', function ($query) use ($learner) {
                $query->where('name', 'like', '%' . $learner . '%');
            });
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'completed') {
                $progressQuery->where('completed', true);
            } elseif ($status === 'in_progress') {
                $progressQuery->where('completed', false)
                    ->where(function ($query) {
                        $query->where('engage_completed', true)
                            ->orWhere('explore_completed', true)
                            ->orWhere('explain_completed', true)
                            ->orWhere('elaborate_completed', true)
                            ->orWhere('evaluate_completed', true);
                    });
            } elseif ($status === 'not_started') {
                $progressQuery->where('completed', false)
                    ->where('engage_completed', false)
                    ->where('explore_completed', false)
                    ->where('explain_completed', false)
                    ->where('elaborate_completed', false)
                    ->where('evaluate_completed', false);
            }
        }

        $sort = $request->string('sort')->toString();
        if ($sort === 'name_asc') {
            $progressQuery
                ->join('users', 'lesson_progress.user_id', '=', 'users.id')
                ->select('lesson_progress.*')
                ->orderBy('users.name');
        } elseif ($sort === 'progress_desc') {
            $progressQuery->orderByRaw(
                '(CASE WHEN engage_completed THEN 1 ELSE 0 END
                + CASE WHEN explore_completed THEN 1 ELSE 0 END
                + CASE WHEN explain_completed THEN 1 ELSE 0 END
                + CASE WHEN elaborate_completed THEN 1 ELSE 0 END
                + CASE WHEN evaluate_completed THEN 1 ELSE 0 END) DESC'
            );
        } else {
            $progressQuery->orderByDesc('updated_at');
        }

        $allProgress = $progressQuery->get();
        $userIds = $allProgress->pluck('user_id')->all();

        $exploreCountByUser = collect();
        if (!empty($userIds)) {
            $exploreCountByUser = \App\Models\LessonActivityCompletion::query()
                ->where('lesson_id', $lesson->id)
                ->where('stage', 'explore')
                ->whereIn('user_id', $userIds)
                ->selectRaw('user_id, count(*) as completed_count')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        }

        $filename = 'lesson-' . $lesson->id . '-learners-' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($allProgress, $exploreCountByUser, $totalActivities) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Learner',
                'Engage',
                'Explore',
                'Explain',
                'Elaborate',
                'Evaluate',
                'Explore Activities Done',
                'Explore Activities Total',
                'Overall Progress (%)',
                'Lesson Completed',
                'Last Updated',
            ]);

            foreach ($allProgress as $progress) {
                $completedStages = collect([
                    $progress->engage_completed,
                    $progress->explore_completed,
                    $progress->explain_completed,
                    $progress->elaborate_completed,
                    $progress->evaluate_completed,
                ])->filter()->count();

                $exploreCount = (int) ($exploreCountByUser->get($progress->user_id)->completed_count ?? 0);

                fputcsv($handle, [
                    $progress->user?->name ?? 'Unknown',
                    $progress->engage_completed ? 'Yes' : 'No',
                    $progress->explore_completed ? 'Yes' : 'No',
                    $progress->explain_completed ? 'Yes' : 'No',
                    $progress->elaborate_completed ? 'Yes' : 'No',
                    $progress->evaluate_completed ? 'Yes' : 'No',
                    $exploreCount,
                    $totalActivities,
                    round(($completedStages / 5) * 100),
                    $progress->completed ? 'Yes' : 'No',
                    optional($progress->updated_at)->toDateTimeString() ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function showStudentActivity(Lesson $lesson, User $student)
    {
        abort_unless((int) $lesson->teacher_id === (int) Auth::id(), 403);

        $progress = LessonProgress::query()
            ->where('user_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->firstOrFail();

        $chatByStage = AiChatMessage::query()
            ->where('user_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->orderBy('id')
            ->get()
            ->groupBy('stage');

        $quizAttempts = QuizAttempt::query()
            ->with('question')
            ->where('user_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->get();

        $engageMcqAttempt = EngageMcqAttempt::query()
            ->where('user_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->latest('id')
            ->first();

        return view('dashboard.teacher.student_activity', [
            'lesson'           => $lesson,
            'student'          => $student,
            'progress'         => $progress,
            'stages'           => ['engage', 'explore', 'explain', 'elaborate', 'evaluate'],
            'chatByStage'      => $chatByStage,
            'quizAttempts'     => $quizAttempts,
            'engageMcqAttempt' => $engageMcqAttempt,
        ]);
    }
}