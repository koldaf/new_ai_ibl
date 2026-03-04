<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LessonStageController;

Route::get('/', function () {
    return view('auth.login');
});
//auth
Route::get('/login', [AuthController::class, 'ShowLogin'])->name('login');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

//dashboard routes with middleware
Route::middleware('auth')->group(function () {
    //admin dashboard
    Route::get('/dashboard/home', [DashboardController::class, 'home'])->name('dashboard.home');
});

Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::resource('lessons', LessonController::class)->except(['show']);
    Route::get('lessons', [LessonController::class, 'index'])->name('lessons.index');
    Route::get('lessons/create', [LessonController::class, 'create'])->name('lessons.create');
    Route::get('lessons/{lesson}/edit', [LessonController::class, 'edit'])->name('lessons.edit');
    Route::post('lessons/{lesson}/stages/{stage}/text', [LessonStageController::class, 'updateText'])->name('lessons.stages.text');
    Route::post('lessons/{lesson}/stages/{stage}/media', [LessonStageController::class, 'uploadMedia'])->name('lessons.stages.media');
    Route::delete('lessons/media/{media}', [LessonStageController::class, 'destroyMedia'])->name('lessons.media.destroy');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->name('logout');