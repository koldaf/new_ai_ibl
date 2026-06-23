<?php

namespace Tests\Unit;

use App\Models\Lesson;
use App\Models\User;
use App\Services\InquiryPhaseAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryPhaseAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_question_and_evidence_counts(): void
    {
        $service = app(InquiryPhaseAnalyticsService::class);
        $user = User::factory()->create();
        $lesson = Lesson::create([
            'title' => 'Force and Motion',
            'description' => 'Lesson description',
        ]);

        $service->recordQuestion($user, $lesson, 'explore', 3);

        $analytic = $lesson->phaseAnalytics()->where('user_id', $user->id)->where('stage', 'explore')->first();

        $this->assertNotNull($analytic);
        $this->assertSame(1, $analytic->questions_generated);
        $this->assertSame(3, $analytic->evidence_sources_consulted);
    }

    public function test_it_scores_reflection_and_allows_teacher_override(): void
    {
        $service = app(InquiryPhaseAnalyticsService::class);
        $student = User::factory()->create();
        $lesson = Lesson::create([
            'title' => 'Photosynthesis',
            'description' => 'Lesson description',
        ]);

        $analytic = $service->saveReflection(
            $student,
            $lesson,
            'explain',
            'I learned that plants convert light to energy because chlorophyll captures sunlight. The evidence from our experiment showed oxygen bubbles increased. Next time I will compare conditions and explain my reasoning better.'
        );

        $this->assertNotNull($analytic->reflection_quality_auto);
        $this->assertGreaterThan(0, $analytic->reflection_quality_auto);
        $this->assertSame($analytic->reflection_quality_auto, $analytic->reflection_quality_final);

        $updated = $service->setTeacherReflectionScore($student, $lesson, 'explain', 92);

        $this->assertSame(92, $updated->reflection_quality_teacher);
        $this->assertSame(92, $updated->reflection_quality_final);
    }

    public function test_it_derives_evidence_count_from_citations_and_links(): void
    {
        $service = app(InquiryPhaseAnalyticsService::class);

        $count = $service->deriveEvidenceCountFromResponse(
            'Based on [1] and [2], and this source https://example.com/report, the result is consistent.'
        );

        $this->assertSame(3, $count);
    }

    public function test_it_ignores_long_idle_gap_when_accumulating_stage_time(): void
    {
        Carbon::setTestNow('2026-06-03 09:00:00');

        try {
            $service = app(InquiryPhaseAnalyticsService::class);
            $user = User::factory()->create();
            $lesson = Lesson::create([
                'title' => 'Plate Tectonics',
                'description' => 'Lesson description',
            ]);

            $service->touchStage($user, $lesson, 'explore');

            Carbon::setTestNow('2026-06-03 09:02:00');
            $service->touchStage($user, $lesson, 'explore');

            Carbon::setTestNow('2026-06-03 11:35:00');
            $analytic = $service->touchStage($user, $lesson, 'explore');

            $this->assertSame(120, (int) $analytic->time_spent_seconds);
        } finally {
            Carbon::setTestNow();
        }
    }
}
