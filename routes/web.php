<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LessonStageController;
use App\Http\Controllers\Students\LessonController as StudentLessonController;
use App\Http\Controllers\Students\QuizController;
use App\Http\Controllers\Students\AIChatController;

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
                Route::post('/{stage}/engage-mcq', [LessonStageController::class, 'upsertEngageMcq'])
                    ->name('engage-mcq.upsert');
                Route::delete('/{stage}/engage-mcq', [LessonStageController::class, 'destroyEngageMcq'])
                    ->name('engage-mcq.destroy');
            });
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