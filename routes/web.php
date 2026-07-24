<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LessonStageController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AiPerformanceController;
use App\Http\Controllers\Admin\LoadTestController;
use App\Http\Controllers\Admin\ClassificationReviewController;
use App\Http\Controllers\Students\LessonController as StudentLessonController;
use App\Http\Controllers\Students\QuizController;
use App\Http\Controllers\Students\AIChatController;
use App\Http\Controllers\Teachers\DashboardController as TeacherDashboardController;

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login'])->name('login.submit');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthController::class, 'register'])->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])   // swap in your actual role middleware
    ->group(function () {

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        Route::resource('users', UserController::class)->except(['show']);

        Route::get('/settings', [SettingsController::class, 'index'])
            ->name('settings.index');
        Route::patch('/settings', [SettingsController::class, 'update'])
            ->name('settings.update');

        // Lessons — exclude 'show' since admins manage, not view
        Route::resource('lessons', LessonController::class)
            ->except(['show']);

        Route::post('/editor-images', [LessonStageController::class, 'uploadEditorImage'])
            ->name('editor-images.store');

        // Lesson-level checkpoint management (centralized in lesson edit)
        Route::prefix('lessons/{lesson}/checkpoint')
            ->name('lessons.checkpoint.')
            ->group(function () {
                Route::post('/questions', [LessonStageController::class, 'storeLessonCheckpointQuestion'])
                    ->name('questions.store');
                Route::patch('/questions/{question}', [LessonStageController::class, 'updateLessonCheckpointQuestion'])
                    ->name('questions.update');
                Route::delete('/questions/{question}', [LessonStageController::class, 'destroyLessonCheckpointQuestion'])
                    ->name('questions.destroy');

                Route::post('/corpus', [LessonStageController::class, 'uploadLessonCheckpointCorpus'])
                    ->name('corpus.store');
                Route::delete('/corpus/{corpus}', [LessonStageController::class, 'destroyLessonCheckpointCorpus'])
                    ->name('corpus.destroy');
                Route::get('/corpus/{corpus}/status', [LessonStageController::class, 'getLessonCheckpointCorpusStatus'])
                    ->name('corpus.status');
                Route::post('/corpus/{corpus}/reprocess', [LessonStageController::class, 'reprocessLessonCheckpointCorpus'])
                    ->name('corpus.reprocess');
            });

        // Lesson stages — scoped under a lesson
        Route::prefix('lessons/{lesson}/stages')
            ->name('lessons.stages.')
            ->group(function () {
                Route::get('/', [LessonStageController::class, 'index'])
                    ->name('index');
                Route::post('/', [LessonStageController::class, 'store'])
                    ->name('store');
                Route::get('/{stage}', [LessonStageController::class, 'show'])
                    ->name('show');
                Route::patch('/{stage}', [LessonStageController::class, 'update'])
                    ->name('update');
                Route::delete('/{stage}', [LessonStageController::class, 'destroy'])
                    ->name('destroy');
                Route::post('/{stage}/text', [LessonStageController::class, 'updateText'])
                    ->name('text');
                Route::post('/{stage}/media', [LessonStageController::class, 'uploadMedia'])
                    ->name('media.store');
                Route::delete('/{stage}/media/{media}', [LessonStageController::class, 'destroyMedia'])
                    ->name('media.destroy');
                Route::post('/{stage}/quiz', [LessonStageController::class, 'updateQuiz'])
                    ->name('quiz');
               //Misconception  admin.lessons.stages.misconceptions.store
                Route::post('/{stage}/misconceptions', [LessonStageController::class, 'storeMisconception'])
                    ->name('misconceptions.store');
                Route::patch('/{stage}/misconceptions/{misconception}', [LessonStageController::class, 'updateMisconception'])
                    ->name('misconceptions.update');
                Route::delete('/{stage}/misconceptions/{misconception}', [LessonStageController::class, 'destroyMisconception'])
                    ->name('misconceptions.destroy');
                Route::post('/{stage}/engage-mcq', [LessonStageController::class, 'upsertEngageMcq'])
                    ->name('engage-mcq.upsert');
                Route::delete('/{stage}/engage-mcq', [LessonStageController::class, 'destroyEngageMcq'])
                    ->name('engage-mcq.destroy');
            });

        // AI Performance Monitor
        Route::get('/ai-performance', [AiPerformanceController::class, 'index'])
            ->name('ai-performance.index');
        Route::get('/ai-performance/live-stats', [AiPerformanceController::class, 'liveStats'])
            ->name('ai-performance.live-stats');
        Route::get('/ai-performance/chart-data', [AiPerformanceController::class, 'chartData'])
            ->name('ai-performance.chart-data');
        Route::get('/ai-performance/export', [AiPerformanceController::class, 'exportLogs'])
            ->name('ai-performance.export');

        // Concurrent-user load testing against the real RAG pipeline
        Route::get('/load-test', [LoadTestController::class, 'index'])
            ->name('load-test.index');
        Route::post('/load-test/run', [LoadTestController::class, 'run'])
            ->name('load-test.run');

        // Human review of AI classifications — builds the verified dataset needed for fine-tuning
        Route::get('/classification-reviews', [ClassificationReviewController::class, 'index'])
            ->name('classification-reviews.index');
        Route::post('/classification-reviews/{message}/review', [ClassificationReviewController::class, 'review'])
            ->name('classification-reviews.review');
    });

/*
|--------------------------------------------------------------------------
| Student routes
|--------------------------------------------------------------------------
*/

Route::prefix('student')
    ->name('student.')
    ->middleware(['auth', 'role:student'])  // swap in your actual role middleware
    ->group(function () {

        // Lessons
        Route::get('/lessons',         [StudentLessonController::class, 'index'])
            ->name('lessons.index');
        Route::get('/lessons/{lesson}', [StudentLessonController::class, 'show'])
            ->name('lessons.show');

        // Stage progress
        Route::post('/lessons/{lesson}/stages/{stage}/complete',
            [StudentLessonController::class, 'completeStage'])
            ->name('lessons.stages.complete');  // consistent plural 'stages' //student.lessons.quiz.submit

        Route::post('/lessons/{lesson}/stages/{stage}/activities/complete',
            [StudentLessonController::class, 'completeActivity'])
            ->name('lessons.stages.activities.complete');

        Route::post('/lessons/{lesson}/stages/{stage}/analytics/touch',
            [StudentLessonController::class, 'touchStage'])
            ->name('lessons.stages.analytics.touch');

        Route::post('/lessons/{lesson}/stages/{stage}/reflection',
            [StudentLessonController::class, 'saveReflection'])
            ->name('lessons.stages.reflection.save');

        // Quiz — own controller, own namespace
        Route::post('/lessons/{lesson}/quiz',
            [QuizController::class, 'submit'])
            ->name('lessons.quiz.submit');

        Route::post('/lessons/{lesson}/engage-mcq',
            [StudentLessonController::class, 'submitEngageMcq'])
            ->name('lessons.engage-mcq.submit');

        // AI Chat
        Route::post('/lessons/{lesson}/ai/ask',
            [AIChatController::class, 'ask'])
            ->name('lessons.ai.ask');
        Route::post('/lessons/{lesson}/ai/ask-stream',
            [AIChatController::class, 'askStream'])
            ->name('lessons.ai.ask-stream');
    });

Route::prefix('teacher')
    ->name('teacher.')
    ->middleware(['auth', 'role:teacher'])
    ->group(function () {
        Route::get('/dashboard', [TeacherDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/lessons/{lesson}', [TeacherDashboardController::class, 'showLesson'])
            ->name('lessons.show');

        Route::get('/lessons/{lesson}/export', [TeacherDashboardController::class, 'exportLesson'])
            ->name('lessons.export');

        Route::get('/lessons/{lesson}/students/{student}', [TeacherDashboardController::class, 'showStudentActivity'])
            ->name('lessons.student-activity');

        Route::post('/lessons/{lesson}/students/{student}/stages/{stage}/reflection-score', [TeacherDashboardController::class, 'updateReflectionScore'])
            ->name('lessons.student-reflection-score.update');
    });