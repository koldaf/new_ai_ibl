<?php

namespace Tests\Unit;

use App\Models\AiChatMessage;
use App\Models\Lesson;
use App\Models\LessonStageContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExportClassificationTrainingDataTest extends TestCase
{
    use RefreshDatabase;

    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputPath = 'storage/app/finetune/test-dataset-' . uniqid() . '.jsonl';
    }

    protected function tearDown(): void
    {
        @unlink(base_path($this->outputPath));
        parent::tearDown();
    }

    #[Test]
    public function it_errors_when_there_are_no_reviewed_messages(): void
    {
        $this->artisan('ai:export-classification-training-data', ['--output' => $this->outputPath])
            ->assertFailed();

        $this->assertFileDoesNotExist(base_path($this->outputPath));
    }

    #[Test]
    public function it_exports_confirmed_and_corrected_rows_with_the_right_targets(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $lesson = Lesson::create(['title' => 'Energy', 'description' => 'Export test']);

        LessonStageContent::create([
            'lesson_id' => $lesson->id,
            'stage' => 'engage',
            'content_type' => 'text',
            'content' => 'Eskom electricity disappeared. Where is the energy hiding?',
        ]);

        // Confirmed correct — original classification/feedback becomes the target.
        AiChatMessage::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'engage',
            'question' => 'Where is the energy?',
            'answer' => 'Energy is stored in chemical form inside batteries and fuel.',
            'classification' => 'correct',
            'confidence' => 0.78,
            'feedback_text' => 'Good, that identifies chemical energy stores.',
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'review_verdict' => 'correct',
        ]);

        // Marked incorrect — corrected_classification becomes the target instead.
        AiChatMessage::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'engage',
            'question' => 'Where is the energy?',
            'answer' => 'All this energy are inside substances like paraffin, petrol, and batteries.',
            'classification' => 'off_topic',
            'confidence' => 0.4,
            'feedback_text' => 'Off topic feedback that was wrong.',
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'review_verdict' => 'incorrect',
            'corrected_classification' => 'correct',
            'review_notes' => 'This is actually correct, just different wording.',
        ]);

        $this->artisan('ai:export-classification-training-data', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $lines = array_filter(explode("\n", file_get_contents(base_path($this->outputPath))));
        $this->assertCount(2, $lines);

        $rows = array_map(fn ($line) => json_decode($line, true), $lines);

        $confirmedRow = collect($rows)->first(fn ($row) => str_contains($row['messages'][2]['content'], '"classification":"correct"') && str_contains($row['messages'][1]['content'], 'batteries and fuel'));
        $correctedRow = collect($rows)->first(fn ($row) => str_contains($row['messages'][1]['content'], 'paraffin, petrol'));

        $this->assertNotNull($confirmedRow);
        $this->assertNotNull($correctedRow);

        $confirmedTarget = json_decode($confirmedRow['messages'][2]['content'], true);
        $this->assertSame('correct', $confirmedTarget['classification']);
        $this->assertSame('Good, that identifies chemical energy stores.', $confirmedTarget['feedback']);

        // The corrected row's target must use the human correction, not the AI's original wrong verdict.
        $correctedTarget = json_decode($correctedRow['messages'][2]['content'], true);
        $this->assertSame('correct', $correctedTarget['classification']);
        $this->assertSame('This is actually correct, just different wording.', $correctedTarget['feedback']);
    }

    #[Test]
    public function it_skips_messages_without_lesson_stage_content(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $lesson = Lesson::create(['title' => 'Energy', 'description' => 'No stage content']);

        AiChatMessage::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'engage',
            'question' => 'Where is the energy?',
            'answer' => 'Energy is stored in chemical form inside batteries and fuel.',
            'classification' => 'correct',
            'confidence' => 0.78,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'review_verdict' => 'correct',
        ]);

        $this->artisan('ai:export-classification-training-data', ['--output' => $this->outputPath])
            ->assertFailed();
    }
}
