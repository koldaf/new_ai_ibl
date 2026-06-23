<?php

namespace Tests\Feature;

use App\Jobs\ProcessCheckpointCorpusEmbedding;
use App\Models\Lesson;
use App\Models\LessonCheckpointCorpus;
use App\Models\LessonCheckpointQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminCheckpointConfigurationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_manage_checkpoint_questions_and_upload_stage_corpus(): void
    {
        Storage::fake('public');
        Queue::fake();

        $admin = User::query()->forceCreate($this->userPayload('Checkpoint Admin', 'admin', 'checkpoint-admin@example.test'));
        $lesson = Lesson::create([
            'title' => 'Light and Shadows',
            'description' => 'Lesson for checkpoint setup',
        ]);

        $storeResponse = $this->actingAs($admin)
            ->postJson(route('admin.lessons.stages.checkpoint-questions.store', ['lesson' => $lesson, 'stage' => 'explore']), [
                'question_text' => 'What pattern did you notice first?',
                'sort_order' => 2,
                'is_active' => true,
            ]);

        $storeResponse
            ->assertOk()
            ->assertJsonPath('data.question_text', 'What pattern did you notice first?')
            ->assertJsonPath('data.is_active', true);

        $questionId = $storeResponse->json('data.id');

        $this->assertDatabaseHas('lesson_checkpoint_questions', [
            'id' => $questionId,
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.lessons.stages.checkpoint-questions.update', ['lesson' => $lesson, 'stage' => 'explore', 'question' => $questionId]), [
                'question_text' => 'Which observation matters most here?',
                'sort_order' => 1,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.question_text', 'Which observation matters most here?')
            ->assertJsonPath('data.is_active', false);

        $file = UploadedFile::fake()->create('checkpoint-notes.txt', 4, 'text/plain');

        $uploadResponse = $this->actingAs($admin)
            ->post(route('admin.lessons.stages.checkpoint-corpus.store', ['lesson' => $lesson, 'stage' => 'explore']), [
                'title' => 'Observation notes',
                'description' => 'Checkpoint-only evidence',
                'sort_order' => 3,
                'file' => $file,
            ]);

        $uploadResponse
            ->assertOk()
            ->assertJsonPath('data.title', 'Observation notes')
            ->assertJsonPath('data.file_type', 'txt')
            ->assertJsonPath('data.processing_status', 'pending');

        $corpusId = $uploadResponse->json('data.id');
        $corpus = LessonCheckpointCorpus::findOrFail($corpusId);

        $this->assertTrue(Storage::disk('public')->exists($corpus->file_path));

        Queue::assertPushed(ProcessCheckpointCorpusEmbedding::class, function (ProcessCheckpointCorpusEmbedding $job) use ($corpusId) {
            return $job->corpus->id === $corpusId;
        });

        $this->actingAs($admin)
            ->deleteJson(route('admin.lessons.stages.checkpoint-questions.destroy', ['lesson' => $lesson, 'stage' => 'explore', 'question' => $questionId]))
            ->assertOk();

        $this->assertDatabaseMissing('lesson_checkpoint_questions', [
            'id' => $questionId,
        ]);
    }

    private function userPayload(string $name, string $role, string $email): array
    {
        return [
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
        ];
    }
}