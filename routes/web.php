<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LessonStageController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AiPerformanceController;
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
                Route::post('/{stage}/checkpoint-questions', [LessonStageController::class, 'storeCheckpointQuestion'])
                    ->name('checkpoint-questions.store');
                Route::patch('/{stage}/checkpoint-questions/{question}', [LessonStageController::class, 'updateCheckpointQuestion'])
                    ->name('checkpoint-questions.update');
                Route::delete('/{stage}/checkpoint-questions/{question}', [LessonStageController::class, 'destroyCheckpointQuestion'])
                    ->name('checkpoint-questions.destroy');
                Route::post('/{stage}/checkpoint-corpus', [LessonStageController::class, 'uploadCheckpointCorpus'])
                    ->name('checkpoint-corpus.store');
                Route::delete('/{stage}/checkpoint-corpus/{corpus}', [LessonStageController::class, 'destroyCheckpointCorpus'])
                    ->name('checkpoint-corpus.destroy');
                Route::get('/{stage}/checkpoint-corpus/{corpus}/status', [LessonStageController::class, 'getCheckpointCorpusStatus'])
                    ->name('checkpoint-corpus.status');
                Route::post('/{stage}/checkpoint-corpus/{corpus}/reprocess', [LessonStageController::class, 'reprocessCheckpointCorpus'])
                    ->name('checkpoint-corpus.reprocess');
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
    });