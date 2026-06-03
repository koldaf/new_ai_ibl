<?php

namespace App\Http\Controllers\Teachers;

use App\Http\Controllers\Controller;
use App\Models\AiChatMessage;
use App\Models\EngageMcqAttempt;
use App\Models\Lesson;
use App\Models\LessonPhaseAnalytic;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\InquiryPhaseAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private const BLOOM_ORDER = ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'];
    private const STAGE_ORDER = ['engage', 'explore', 'explain', 'elaborate', 'evaluate'];

    public function __construct(private InquiryPhaseAnalyticsService $inquiryAnalytics)
    {
    }

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
        $filteredUserIds = $allProgress->pluck('user_id')->all();
        $stagePercentages = [
            'engage' => $totalLearners > 0 ? round(($allProgress->where('engage_completed', true)->count() / $totalLearners) * 100) : 0,
            'explore' => $totalLearners > 0 ? round(($allProgress->where('explore_completed', true)->count() / $totalLearners) * 100) : 0,
            'explain' => $totalLearners > 0 ? round(($allProgress->where('explain_completed', true)->count() / $totalLearners) * 100) : 0,
            'elaborate' => $totalLearners > 0 ? round(($allProgress->where('elaborate_completed', true)->count() / $totalLearners) * 100) : 0,
            'evaluate' => $totalLearners > 0 ? round(($allProgress->where('evaluate_completed', true)->count() / $totalLearners) * 100) : 0,
        ];

        $lessonBloomMessages = collect();
        if (!empty($filteredUserIds)) {
            $lessonBloomMessages = AiChatMessage::query()
                ->where('lesson_id', $lesson->id)
                ->whereIn('user_id', $filteredUserIds)
                ->whereNotNull('bloom_level')
                ->whereNotIn('question', ['__engage_start__', '__checkpoint_start__'])
                ->get();
        }

        $lessonBloomStats = collect(self::BLOOM_ORDER)->map(function (string $level) use ($lessonBloomMessages) {
            $rows = $lessonBloomMessages->where('bloom_level', $level);

            return [
                'level' => $level,
                'count' => $rows->count(),
                'avg_confidence' => $rows->count() > 0
                    ? round((float) $rows->avg('bloom_confidence'), 2)
                    : null,
            ];
        });

        $lessonBloomByStage = $lessonBloomMessages
            ->groupBy('stage')
            ->map(function ($rows) {
                return collect(self::BLOOM_ORDER)
                    ->mapWithKeys(fn (string $level) => [$level => (int) $rows->where('bloom_level', $level)->count()]);
            });

        $phaseAnalytics = collect();
        if (! empty($filteredUserIds)) {
            $phaseAnalytics = LessonPhaseAnalytic::query()
                ->where('lesson_id', $lesson->id)
                ->whereIn('user_id', $filteredUserIds)
                ->get();
        }

        $phaseAnalyticsByStage = $phaseAnalytics
            ->groupBy('stage')
            ->map(function ($rows) {
                return [
                    'avg_time_minutes' => round(((float) $rows->avg('time_spent_seconds')) / 60, 1),
                    'avg_questions' => round((float) $rows->avg('questions_generated'), 1),
                    'avg_evidence' => round((float) $rows->avg('evidence_sources_consulted'), 1),
                    'avg_reflection_quality' => round((float) $rows->avg('reflection_quality_final'), 1),
                    'avg_evaluation_final_score' => round((float) $rows->avg('evaluation_final_score'), 1),
                    'reflection_count' => $rows->filter(fn (LessonPhaseAnalytic $item) => filled($item->reflection_text))->count(),
                ];
            });

        $phaseMetricsByUser = $phaseAnalytics
            ->groupBy('user_id')
            ->map(function ($rows) {
                return $rows
                    ->keyBy('stage')
                    ->map(function (LessonPhaseAnalytic $analytic) {
                        return [
                            'time_minutes' => round($analytic->time_spent_seconds / 60, 1),
                            'questions_generated' => (int) $analytic->questions_generated,
                            'evidence_sources_consulted' => (int) $analytic->evidence_sources_consulted,
                            'reflection_quality_final' => $analytic->reflection_quality_final,
                            'evaluation_final_score' => $analytic->evaluation_final_score,
                            'has_reflection' => filled($analytic->reflection_text),
                        ];
                    });
            });

        $bloomByUser = $lessonBloomMessages
            ->groupBy('user_id')
            ->map(function ($messages) {
                $countByLevel = collect(self::BLOOM_ORDER)
                    ->mapWithKeys(fn (string $level) => [$level => (int) $messages->where('bloom_level', $level)->count()]);

                $total = (int) $messages->count();

                return [
                    'total' => $total,
                    'count_by_level' => $countByLevel,
                    'dominant_level' => $total > 0
                        ? (string) $countByLevel->sortDesc()->keys()->first()
                        : null,
                    'avg_confidence' => $total > 0
                        ? round((float) $messages->avg('bloom_confidence'), 2)
                        : null,
                ];
            });

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

        $studentRows = $paginatedProgress->getCollection()->map(function (LessonProgress $progress) use ($exploreActivities, $exploreCompletionByUser, $bloomByUser, $phaseMetricsByUser) {
            $completedStages = collect([
                $progress->engage_completed,
                $progress->explore_completed,
                $progress->explain_completed,
                $progress->elaborate_completed,
                $progress->evaluate_completed,
            ])->filter()->count();

            $overallProgress = round(($completedStages / 5) * 100);
            $exploreCount = (int) ($exploreCompletionByUser->get($progress->user_id)->completed_count ?? 0);
            $inquiryByStage = $phaseMetricsByUser->get($progress->user_id, collect());
            $inquiryAverages = [
                'time_minutes' => round((float) $inquiryByStage->avg('time_minutes'), 1),
                'questions_generated' => round((float) $inquiryByStage->avg('questions_generated'), 1),
                'evidence_sources_consulted' => round((float) $inquiryByStage->avg('evidence_sources_consulted'), 1),
                'reflection_quality_final' => round((float) $inquiryByStage->avg('reflection_quality_final'), 1),
                'evaluation_final_score' => round((float) $inquiryByStage->avg('evaluation_final_score'), 1),
            ];

            return [
                'progress' => $progress,
                'user' => $progress->user,
                'overall_progress' => $overallProgress,
                'explore_completed_count' => $exploreCount,
                'explore_total_count' => $exploreActivities->count(),
                'bloom_profile' => $bloomByUser->get($progress->user_id, [
                    'total' => 0,
                    'count_by_level' => collect(self::BLOOM_ORDER)->mapWithKeys(fn (string $level) => [$level => 0]),
                    'dominant_level' => null,
                    'avg_confidence' => null,
                ]),
                'inquiry_profile' => [
                    'by_stage' => $inquiryByStage,
                    'averages' => $inquiryAverages,
                ],
            ];
        });

        $paginatedProgress->setCollection($studentRows);

        return view('dashboard.teacher.lesson', [
            'lesson' => $lesson,
            'exploreActivities' => $exploreActivities,
            'studentRows' => $paginatedProgress,
            'stagePercentages' => $stagePercentages,
            'lessonBloomStats' => $lessonBloomStats,
            'lessonBloomByStage' => $lessonBloomByStage,
            'phaseAnalyticsByStage' => $phaseAnalyticsByStage,
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
        $stageOrder = self::STAGE_ORDER;
        $bloomLevels = self::BLOOM_ORDER;

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

        $bloomByUser = collect();
        if (!empty($userIds)) {
            $bloomMessages = AiChatMessage::query()
                ->where('lesson_id', $lesson->id)
                ->whereIn('user_id', $userIds)
                ->whereNotNull('bloom_level')
                ->whereNotIn('question', ['__engage_start__', '__checkpoint_start__'])
                ->get();

            $bloomByUser = $bloomMessages
                ->groupBy('user_id')
                ->map(function ($messages) use ($bloomLevels) {
                    $countByLevel = collect($bloomLevels)
                        ->mapWithKeys(fn (string $level) => [$level => (int) $messages->where('bloom_level', $level)->count()]);

                    $total = (int) $messages->count();

                    return [
                        'total' => $total,
                        'count_by_level' => $countByLevel,
                        'dominant_level' => $total > 0
                            ? (string) $countByLevel->sortDesc()->keys()->first()
                            : null,
                        'avg_confidence' => $total > 0
                            ? round((float) $messages->avg('bloom_confidence'), 2)
                            : null,
                    ];
                });
        }

        $phaseByUser = collect();
        if (!empty($userIds)) {
            $phaseByUser = LessonPhaseAnalytic::query()
                ->where('lesson_id', $lesson->id)
                ->whereIn('user_id', $userIds)
                ->get()
                ->groupBy('user_id')
                ->map(fn ($rows) => $rows->keyBy('stage'));
        }

        $filename = 'lesson-' . $lesson->id . '-learners-' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($allProgress, $exploreCountByUser, $totalActivities, $bloomByUser, $phaseByUser, $stageOrder, $bloomLevels) {
            $handle = fopen('php://output', 'w');

            $headers = [
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
                'Bloom Total Questions',
                'Bloom Dominant Level',
                'Bloom Avg Confidence (%)',
                'Inquiry Avg Time (min)',
                'Inquiry Avg Questions',
                'Inquiry Avg Evidence',
                'Inquiry Avg Reflection Final',
                'Inquiry Avg Evaluation Final Score',
                'Inquiry Reflection Count',
                'Last Updated',
            ];

            foreach ($bloomLevels as $level) {
                $headers[] = 'Bloom ' . ucfirst($level) . ' Count';
            }

            foreach ($stageOrder as $stage) {
                $prefix = ucfirst($stage);
                $headers[] = $prefix . ' Time (min)';
                $headers[] = $prefix . ' Questions';
                $headers[] = $prefix . ' Evidence';
                $headers[] = $prefix . ' Reflection Final';
                $headers[] = $prefix . ' Evaluation Final Score';
            }

            fputcsv($handle, $headers);

            foreach ($allProgress as $progress) {
                $completedStages = collect([
                    $progress->engage_completed,
                    $progress->explore_completed,
                    $progress->explain_completed,
                    $progress->elaborate_completed,
                    $progress->evaluate_completed,
                ])->filter()->count();

                $exploreCount = (int) ($exploreCountByUser->get($progress->user_id)->completed_count ?? 0);

                $bloomProfile = $bloomByUser->get($progress->user_id, [
                    'total' => 0,
                    'count_by_level' => collect($bloomLevels)->mapWithKeys(fn (string $level) => [$level => 0]),
                    'dominant_level' => null,
                    'avg_confidence' => null,
                ]);

                $phaseRows = collect($phaseByUser->get($progress->user_id, collect()));
                $inquiryAvg = [
                    'time' => round((float) $phaseRows->avg('time_spent_seconds') / 60, 1),
                    'questions' => round((float) $phaseRows->avg('questions_generated'), 1),
                    'evidence' => round((float) $phaseRows->avg('evidence_sources_consulted'), 1),
                    'reflection_final' => round((float) $phaseRows->avg('reflection_quality_final'), 1),
                    'evaluation_final' => round((float) $phaseRows->avg('evaluation_final_score'), 1),
                    'reflection_count' => (int) $phaseRows->filter(fn (LessonPhaseAnalytic $row) => filled($row->reflection_text))->count(),
                ];

                $row = [
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
                    (int) ($bloomProfile['total'] ?? 0),
                    $bloomProfile['dominant_level'] ? ucfirst((string) $bloomProfile['dominant_level']) : '',
                    !is_null($bloomProfile['avg_confidence']) ? (int) round(((float) $bloomProfile['avg_confidence']) * 100) : '',
                    $inquiryAvg['time'],
                    $inquiryAvg['questions'],
                    $inquiryAvg['evidence'],
                    $inquiryAvg['reflection_final'],
                    $inquiryAvg['evaluation_final'],
                    $inquiryAvg['reflection_count'],
                    optional($progress->updated_at)->toDateTimeString() ?? '',
                ];

                foreach ($bloomLevels as $level) {
                    $row[] = (int) (($bloomProfile['count_by_level'][$level] ?? 0));
                }

                foreach ($stageOrder as $stage) {
                    /** @var LessonPhaseAnalytic|null $stageRow */
                    $stageRow = $phaseRows->get($stage);
                    $row[] = $stageRow ? round(((float) $stageRow->time_spent_seconds) / 60, 1) : 0;
                    $row[] = $stageRow ? (int) $stageRow->questions_generated : 0;
                    $row[] = $stageRow ? (int) $stageRow->evidence_sources_consulted : 0;
                    $row[] = $stageRow && !is_null($stageRow->reflection_quality_final) ? (int) $stageRow->reflection_quality_final : '';
                    $row[] = $stageRow && !is_null($stageRow->evaluation_final_score) ? (int) $stageRow->evaluation_final_score : '';
                }

                fputcsv($handle, $row);
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

        $learnerBloomMessages = AiChatMessage::query()
            ->where('user_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->whereNotNull('bloom_level')
            ->whereNotIn('question', ['__engage_start__', '__checkpoint_start__'])
            ->get();

        $learnerBloomStats = collect(self::BLOOM_ORDER)->map(function (string $level) use ($learnerBloomMessages) {
            $rows = $learnerBloomMessages->where('bloom_level', $level);

            return [
                'level' => $level,
                'count' => $rows->count(),
                'avg_confidence' => $rows->count() > 0
                    ? round((float) $rows->avg('bloom_confidence'), 2)
                    : null,
            ];
        });

        $learnerBloomByStage = $learnerBloomMessages
            ->groupBy('stage')
            ->map(function ($rows) {
                return collect(self::BLOOM_ORDER)
                    ->mapWithKeys(fn (string $level) => [$level => (int) $rows->where('bloom_level', $level)->count()]);
            });

        $phaseAnalyticsByStage = LessonPhaseAnalytic::query()
            ->where('user_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->get()
            ->keyBy('stage');

        return view('dashboard.teacher.student_activity', [
            'lesson'           => $lesson,
            'student'          => $student,
            'progress'         => $progress,
            'stages'           => ['engage', 'explore', 'explain', 'elaborate', 'evaluate'],
            'chatByStage'      => $chatByStage,
            'quizAttempts'     => $quizAttempts,
            'engageMcqAttempt' => $engageMcqAttempt,
            'learnerBloomStats' => $learnerBloomStats,
            'learnerBloomByStage' => $learnerBloomByStage,
            'phaseAnalyticsByStage' => $phaseAnalyticsByStage,
        ]);
    }

    public function updateReflectionScore(Request $request, Lesson $lesson, User $student, string $stage)
    {
        abort_unless((int) $lesson->teacher_id === (int) Auth::id(), 403);
        abort_unless(in_array($stage, self::STAGE_ORDER, true), 422);

        $isTrackedLearner = LessonProgress::query()
            ->where('lesson_id', $lesson->id)
            ->where('user_id', $student->id)
            ->exists();

        abort_unless($isTrackedLearner, 404);

        $validated = $request->validate([
            'reflection_quality_teacher' => 'required|integer|min:0|max:100',
        ]);

        $analytic = $this->inquiryAnalytics->setTeacherReflectionScore(
            $student,
            $lesson,
            $stage,
            (int) $validated['reflection_quality_teacher']
        );

        return response()->json([
            'success' => true,
            'stage' => $stage,
            'reflection_quality_teacher' => $analytic->reflection_quality_teacher,
            'reflection_quality_final' => $analytic->reflection_quality_final,
        ]);
    }
}