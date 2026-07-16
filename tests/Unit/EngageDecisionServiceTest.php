<?php

namespace Tests\Unit;

use App\Models\Lesson;
use App\Models\LessonCheckpointCorpus;
use App\Models\User;
use App\Services\AiMemoryService;
use App\Services\EngageDecisionService;
use App\Services\RagQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EngageDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_falls_back_to_the_rule_based_classifier_when_the_llm_output_leaks_prompt_template_text(): void
    {
        // Reproduces the real bug: a student answer that inverts the actual law
        // ("energy is created" instead of "cannot be created or destroyed") got
        // graded "correct" at high confidence, with feedback that ended in the
        // literal "...or null" fragment lifted straight from the JSON schema
        // instructions in the prompt.
        $student = User::query()->forceCreate($this->userPayload('Student One', 'student', 'engage-guardrail@example.test'));
        $lesson = Lesson::create([
            'title' => 'Energy',
            'description' => 'Engage guardrail test',
        ]);

        // Production (MySQL) allows stage=NULL here for a lesson-wide corpus; the
        // sqlite test schema still has the original NOT NULL from before that
        // migration (it only ALTERs for the mysql driver). Relax it for this test.
        \Illuminate\Support\Facades\Schema::table('lesson_checkpoint_corpora', function ($table) {
            $table->string('stage')->nullable()->change();
        });

        LessonCheckpointCorpus::create([
            'lesson_id' => $lesson->id,
            'stage' => null,
            'title' => 'Energy corpus',
            'file_path' => 'lessons/energy/corpus.pdf',
            'file_name' => 'corpus.pdf',
            'processing_status' => 'completed',
            'vector_store_path' => 'fake/vector-store.json',
            'sort_order' => 1,
        ]);

        $ragService = Mockery::mock(RagQueryService::class);
        $ragService->shouldReceive('isOllamaHealthy')->once()->andReturn(true);
        $ragService->shouldReceive('retrieveContextFromVectorStoresSafe')
            ->once()
            ->andReturn('Energy conservation states energy cannot be created or destroyed, only transformed between forms.');
        $ragService->shouldReceive('getClassificationModel')->once()->andReturn('qwen2.5:0.5b');
        $ragService->shouldReceive('callLlm')->once()->andReturn(json_encode([
            'classification' => 'correct',
            'confidence' => 1.0,
            'feedback' => 'The student provided a detailed explanation using the law of conservation of energy to ...or null',
            'follow_up' => null,
        ]));

        $service = new EngageDecisionService($ragService, new AiMemoryService());

        $result = $service->assessAnswer(
            $lesson,
            $student,
            'the law states that all forms of energy are created somehow'
        );

        $this->assertStringNotContainsString('or null', $result['answer']);
        // Falls through to the rule-based classifier's fixed confidence values —
        // proof the poisoned 100%-confidence "correct" verdict was discarded,
        // not merely relabeled — and retrieval_mode reflects that no vector
        // retrieval result was actually trusted/used.
        $this->assertNotEquals(1.0, $result['confidence']);
        $this->assertSame('none', $result['retrieval_mode']);
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

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
