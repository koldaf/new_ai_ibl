<?php

namespace App\Models;


// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonActivityCompletion;
use App\Models\QuizAttempt;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'password',
        'role', // student/teacher/admin
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function lessonActivityCompletions()
    {
        return $this->hasMany(LessonActivityCompletion::class);
    }

    public function ownedLessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'teacher_id');
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Check if the user has a given role. The provided role string may
     * contain pipes to indicate alternatives (e.g. 'admin|teacher').
     */
    public function hasRole(string $role): bool
    {
        $roles = explode('|', $role);
        return in_array($this->role, $roles, true);
    }

    /**
     * Convenience helper for common checks. You can add more as needed.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function assignedLessons()
    {
        return $this->belongsToMany(Lesson::class, 'lesson_teacher', 'teacher_id', 'lesson_id')->withTimestamps();
    }
}
