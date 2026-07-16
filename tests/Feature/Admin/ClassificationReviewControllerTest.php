<?php

namespace Tests\Feature\Admin;

use App\Models\AiChatMessage;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassificationReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function students_cannot_access_the_review_page(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('admin.classification-reviews.index'))
            ->assertStatus(403);
    }

    #[Test]
    public function it_lists_only_unreviewed_classified_messages_by_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$student, $lesson, $reviewed, $unreviewed] = $this->seedMessages();

        $response = $this->actingAs($admin)->get(route('admin.classification-reviews.index'));

        $response->assertOk()
            ->assertSee(\Illuminate\Support\Str::limit($unreviewed->answer, 160))
            ->assertDontSee(\Illuminate\Support\Str::limit($reviewed->answer, 160));
    }

    #[Test]
    public function it_lists_only_reviewed_messages_when_filtered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$student, $lesson, $reviewed, $unreviewed] = $this->seedMessages();

        $response = $this->actingAs($admin)->get(route('admin.classification-reviews.index', ['filter' => 'reviewed']));

        $response->assertOk()
            ->assertSee(\Illuminate\Support\Str::limit($reviewed->answer, 160))
            ->assertDontSee(\Illuminate\Support\Str::limit($unreviewed->answer, 160));
    }

    #[Test]
    public function admin_can_confirm_a_classification_as_correct(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$student, $lesson, $reviewed, $unreviewed] = $this->seedMessages();

        $this->actingAs($admin)->post(route('admin.classification-reviews.review', $unreviewed), [
            'verdict' => 'correct',
        ])->assertRedirect();

        $unreviewed->refresh();
        $this->assertSame('correct', $unreviewed->review_verdict);
        $this->assertSame($admin->id, $unreviewed->reviewed_by);
        $this->assertNotNull($unreviewed->reviewed_at);
        $this->assertNull($unreviewed->corrected_classification);
    }

    #[Test]
    public function admin_can_mark_a_classification_incorrect_with_a_correction(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$student, $lesson, $reviewed, $unreviewed] = $this->seedMessages();

        $this->actingAs($admin)->post(route('admin.classification-reviews.review', $unreviewed), [
            'verdict' => 'incorrect',
            'corrected_classification' => 'correct',
            'notes' => 'Answer used different wording but was actually right.',
        ])->assertRedirect();

        $unreviewed->refresh();
        $this->assertSame('incorrect', $unreviewed->review_verdict);
        $this->assertSame('correct', $unreviewed->corrected_classification);
        $this->assertSame('Answer used different wording but was actually right.', $unreviewed->review_notes);
    }

    #[Test]
    public function marking_incorrect_without_a_correction_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$student, $lesson, $reviewed, $unreviewed] = $this->seedMessages();

        $this->actingAs($admin)->post(route('admin.classification-reviews.review', $unreviewed), [
            'verdict' => 'incorrect',
        ])->assertSessionHasErrors('corrected_classification');

        $unreviewed->refresh();
        $this->assertNull($unreviewed->reviewed_at);
    }

    private function seedMessages(): array
    {
        $student = User::factory()->create(['role' => 'student']);
        $lesson = Lesson::create(['title' => 'Energy', 'description' => 'Review test lesson']);

        $reviewed = AiChatMessage::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'engage',
            'question' => 'What do you know about energy?',
            'answer' => 'Energy is stored in chemical form inside batteries and fuel.',
            'classification' => 'correct',
            'confidence' => 0.8,
            'reviewed_at' => now(),
            'reviewed_by' => User::factory()->create(['role' => 'admin'])->id,
            'review_verdict' => 'correct',
        ]);

        $unreviewed = AiChatMessage::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'engage',
            'question' => 'What do you know about energy?',
            'answer' => 'All this energy are inside substances like paraffin, petrol, and batteries.',
            'classification' => 'off_topic',
            'confidence' => 0.4,
        ]);

        return [$student, $lesson, $reviewed, $unreviewed];
    }
}
