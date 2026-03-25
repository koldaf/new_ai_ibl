<?php

namespace App\Services;

use App\Models\AiChatMessage;
use App\Models\AppSetting;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AiMemoryService
{
    public function isEnabled(): bool
    {
        return (bool) AppSetting::getValue('ai_memory_enabled', false);
    }

    public function getHistoryForPrompt(User $user, Lesson $lesson, ?string $stage = null, int $limit = 6): Collection
    {
        $query = AiChatMessage::query()
            ->where('user_id', $user->id)
            ->with('lesson:id,title');

        if (!$this->isEnabled()) {
            $query->where('lesson_id', $lesson->id);
        }

        if ($stage !== null) {
            $query->where('stage', $stage);
        }

        return $query->latest('id')->take($limit)->get()->reverse()->values();
    }

    public function buildPromptContext(Collection $messages, int $currentLessonId): string
    {
        if ($messages->isEmpty()) {
            return '';
        }

        $lines = [];

        foreach ($messages as $message) {
            $lessonLabel = (int) $message->lesson_id === $currentLessonId
                ? 'Current lesson'
                : ($message->lesson?->title ?: 'Previous lesson');

            $lines[] = "[{$lessonLabel} | Stage: {$message->stage}]";
            $lines[] = 'Student: ' . trim((string) $message->question);
            $lines[] = 'AI: ' . trim((string) $message->answer);
        }

        return implode("\n", $lines);
    }
}