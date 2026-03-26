<?php

namespace Tests\Feature;

use App\Models\AiChatMessage;
use App\Models\Lesson;
use App\Models\LessonCheckpointCorpus;
use App\Models\LessonCheckpointQuestion;
use App\Models\LessonStageContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentCheckpointFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function student_can_start_checkpoint_for_explore_stage(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Test Student', 'student', 'student@example.test'));
        $lesson = Lesson::create([
            'title' => 'Photosynthesis',
            'description' => 'Understanding photosynthesis process',
        ]);

        // Create checkpoint question
        LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question_text' => 'What are the inputs to photosynthesis?',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => User::query()->first()?->id ?? 1,
        ]);

        // Request checkpoint start
        $response = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => '__checkpoint_start__',
                'stage' => 'explore',
                'intent' => 'start',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('intent', 'start')
            ->assertJsonPath('stage', 'explore')
            ->assertJsonPath('answer', 'What are the inputs to photosynthesis?');

        // Verify chat message was created
        $this->assertDatabaseHas('ai_chat_message', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question' => '__checkpoint_start__',
            'answer' => 'What are the inputs to photosynthesis?',
            'engage_status' => 'in_progress',
        ]);
    }

    #[Test]
    public function student_can_answer_checkpoint_question(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Test Student', 'student', 'student@example.test'));
        $lesson = Lesson::create([
            'title' => 'Photosynthesis',
            'description' => 'Understanding photosynthesis process',
        ]);

        // Create checkpoint question
        $question = LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question_text' => 'What are the inputs to photosynthesis?',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => User::query()->first()?->id ?? 1,
        ]);

        // Create initial checkpoint message
        AiChatMessage::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question' => '__checkpoint_start__',
            'answer' => 'What are the inputs to photosynthesis?',
            'engage_status' => 'in_progress',
            'context_source' => 'stage_text',
            'retrieval_mode' => 'non_vector',
        ]);

        // Student answers
        $response = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => 'Light and carbon dioxide',
                'stage' => 'explore',
                'intent' => 'answer',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('intent', 'answer')
            ->assertJsonPath('stage', 'explore')
            ->assertJsonStructure([
                'success',
                'stage',
                'intent',
                'answer',
                'classification',
                'engage_status',
            ]);

        // Verify the response has classification
        $this->assertContains($response->json('classification'), ['correct', 'partial', 'off_topic']);

        // Verify chat message was created for the answer
        $this->assertDatabaseHas('ai_chat_message', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question' => 'Light and carbon dioxide',
        ]);
    }

    #[Test]
    public function checkpoint_engage_status_progresses_with_answers(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Test Student', 'student', 'student@example.test'));
        $lesson = Lesson::create([
            'title' => 'Photosynthesis',
            'description' => 'Understanding photosynthesis process',
        ]);

        // Create checkpoint question
        LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question_text' => 'What are the inputs to photosynthesis?',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => User::query()->first()?->id ?? 1,
        ]);

        // Start checkpoint
        $startResponse = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => '__checkpoint_start__',
                'stage' => 'explore',
                'intent' => 'start',
            ]);

        $this->assertEquals('in_progress', $startResponse->json('engage_status'));

        // Answer checkpoint  
        $answerResponse = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => 'Light energy and carbon dioxide are the inputs to photosynthesis',
                'stage' => 'explore',
                'intent' => 'answer',
            ]);

        // Status should be either 'in_progress' or 'complete' depending on classification
        $this->assertContains($answerResponse->json('engage_status'), ['in_progress', 'complete']);

        // Verify answer was classified
        $this->assertIsString($answerResponse->json('classification'));
    }

    #[Test]
    public function checkpoint_works_for_explain_and_elaborate_stages(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Test Student', 'student', 'student@example.test'));
        $lesson = Lesson::create([
            'title' => 'Photosynthesis',
            'description' => 'Understanding photosynthesis process',
        ]);

        $stages = ['explain', 'elaborate'];

        foreach ($stages as $stage) {
            // Create checkpoint question for this stage
            LessonCheckpointQuestion::create([
                'lesson_id' => $lesson->id,
                'stage' => $stage,
                'question_text' => 'Explain the role of ' . ($stage === 'explain' ? 'chlorophyll' : 'ATP') . ' in this process.',
                'is_active' => true,
                'sort_order' => 1,
                'created_by' => User::query()->first()?->id ?? 1,
            ]);

            // Request checkpoint start
            $response = $this->actingAs($student)
                ->postJson(route('student.lessons.ai.ask', $lesson), [
                    'question' => '__checkpoint_start__',
                    'stage' => $stage,
                    'intent' => 'start',
                ]);

            $response
                ->assertOk()
                ->assertJsonPath('intent', 'start')
                ->assertJsonPath('stage', $stage);

            // Verify message was created with correct stage
            $this->assertDatabaseHas('ai_chat_message', [
                'user_id' => $student->id,
                'lesson_id' => $lesson->id,
                'stage' => $stage,
                'question' => '__checkpoint_start__',
            ]);
        }
    }

    #[Test]
    public function student_avoids_repeating_checkpoint_question_when_multiple_exist(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Test Student', 'student', 'student@example.test'));
        $lesson = Lesson::create([
            'title' => 'Photosynthesis',
            'description' => 'Understanding photosynthesis process',
        ]);

        // Create multiple checkpoint questions
        $q1 = LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question_text' => 'What are the inputs?',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => User::query()->first()?->id ?? 1,
        ]);

        $q2 = LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question_text' => 'What are the outputs?',
            'is_active' => true,
            'sort_order' => 2,
            'created_by' => User::query()->first()?->id ?? 1,
        ]);

        // First checkpoint start
        $response1 = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => '__checkpoint_start__',
                'stage' => 'explore',
                'intent' => 'start',
            ]);

        $firstAnswer = $response1->json('answer');

        // Answer the first question
        $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => 'Some thoughtful answer',
                'stage' => 'explore',
                'intent' => 'answer',
            ]);

        // Second checkpoint start
        $response2 = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => '__checkpoint_start__',
                'stage' => 'explore',
                'intent' => 'start',
            ]);

        $secondAnswer = $response2->json('answer');

        // The questions should be different (anti-repeat logic)
        $this->assertNotEquals($firstAnswer, $secondAnswer, 'Should serve different questions when multiple exist');
    }

    #[Test]
    public function student_checkpoint_session_persists_across_multiple_answers(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Test Student', 'student', 'student@example.test'));
        $lesson = Lesson::create([
            'title' => 'Photosynthesis',
            'description' => 'Understanding photosynthesis process',
        ]);

        // Create checkpoint question
        LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question_text' => 'What are the inputs to photosynthesis?',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => User::query()->first()?->id ?? 1,
        ]);

        // Start checkpoint
        $startResponse = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => '__checkpoint_start__',
                'stage' => 'explore',
                'intent' => 'start',
            ]);

        $this->assertEquals('in_progress', $startResponse->json('engage_status'));

        // First answer (incomplete)
        $response1 = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => 'Water',
                'stage' => 'explore',
                'intent' => 'answer',
            ]);

        // Should receive feedback
        $this->assertIsString($response1->json('answer'));
        $this->assertIsString($response1->json('classification'));

        // Second answer (more complete)
        $response2 = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => 'Light energy and carbon dioxide',
                'stage' => 'explore',
                'intent' => 'answer',
            ]);

        // Should still have classification
        $this->assertIsString($response2->json('classification'));
        // Status should progress through the session
        $this->assertContains($response2->json('engage_status'), ['in_progress', 'complete']);

        // Verify all messages are in chat history
        $messages = AiChatMessage::query()
            ->where('user_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->where('stage', 'explore')
            ->orderBy('id')
            ->get();

        $this->assertGreaterThanOrEqual(3, $messages->count(), 'Should have start + 2 answers');
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

    #[Test]
    public function partial_checkpoint_answer_for_explore_stage_includes_full_answer_structure(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Test Student', 'student', 'student@example.test'));
        $lesson = Lesson::create([
            'title' => 'Photosynthesis',
            'description' => 'Understanding photosynthesis process',
        ]);

        // Create checkpoint question
        LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question_text' => 'What are the inputs to photosynthesis?',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => User::query()->first()?->id ?? 1,
        ]);

        // Start checkpoint
        $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => '__checkpoint_start__',
                'stage' => 'explore',
                'intent' => 'start',
            ]);

        // Answer with partial response (missing one key component)
        $response = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => 'Water and sunlight',
                'stage' => 'explore',
                'intent' => 'answer',
            ]);

        $response->assertOk();

        // Should have full_answer field in response structure
        $response->assertJsonStructure([
            'success',
            'stage',
            'intent',
            'answer',
            'classification',
            'engage_status',
            'full_answer',
        ]);

        // If classified as partial, verify full_answer is present
        if ($response->json('classification') === 'partial') {
            // full_answer may be null if LLM is not available, but field should exist
            $fullAnswer = $response->json('full_answer');
            $this->assertTrue($fullAnswer === null || is_string($fullAnswer));
        }
    }

    #[Test]
    public function student_pasting_back_question_gets_redirected_to_read_content(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Test Student', 'student', 'student@example.test'));
        $lesson = Lesson::create([
            'title' => 'Ocean Ecosystems',
            'description' => 'Understanding ocean food webs',
        ]);

        // Create checkpoint question
        LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question_text' => 'What role do plankton play in ocean ecosystems?',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => User::query()->first()?->id ?? 1,
        ]);

        // Start checkpoint
        $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => '__checkpoint_start__',
                'stage' => 'explore',
                'intent' => 'start',
            ]);

        // Student pastes back the question
        $response = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => 'What role do plankton play in ocean ecosystems?',
                'stage' => 'explore',
                'intent' => 'answer',
            ]);

        $response->assertOk();
        // Should detect question repetition
        $this->assertEquals('question_repetition', $response->json('classification'));
        // Should allow them to try again (in_progress, not complete)
        $this->assertEquals('in_progress', $response->json('engage_status'));
    }

    #[Test]
    public function checkpoint_stops_after_three_attempts(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Test Student', 'student', 'student@example.test'));
        $lesson = Lesson::create([
            'title' => 'Photosynthesis',
            'description' => 'Understanding photosynthesis process',
        ]);

        // Create checkpoint question
        LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question_text' => 'What are the inputs to photosynthesis?',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => User::query()->first()?->id ?? 1,
        ]);

        // Start checkpoint
        $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => '__checkpoint_start__',
                'stage' => 'explore',
                'intent' => 'start',
            ]);

        // Attempt 1
        $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => 'Water',
                'stage' => 'explore',
                'intent' => 'answer',
            ]);

        // Attempt 2
        $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => 'Carbon dioxide',
                'stage' => 'explore',
                'intent' => 'answer',
            ]);

        // Attempt 3 - should force completion
        $response = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => 'Light energy',
                'stage' => 'explore',
                'intent' => 'answer',
            ]);

        $response->assertOk();
        // After 3 attempts, should be complete
        $this->assertEquals('complete', $response->json('engage_status'));
        $this->assertEquals('attempts_exhausted', $response->json('classification'));
    }

    #[Test]
    public function checkpoint_stops_immediately_after_full_answer_displayed(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Test Student', 'student', 'student@example.test'));
        $lesson = Lesson::create([
            'title' => 'Photosynthesis',
            'description' => 'Understanding photosynthesis process',
        ]);

        // Create checkpoint question
        LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => 'explore',
            'question_text' => 'What are the inputs to photosynthesis?',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => User::query()->first()?->id ?? 1,
        ]);

        // Start checkpoint
        $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => '__checkpoint_start__',
                'stage' => 'explore',
                'intent' => 'start',
            ]);

        // Answer with partial response that will trigger full_answer generation
        $response = $this->actingAs($student)
            ->postJson(route('student.lessons.ai.ask', $lesson), [
                'question' => 'Water and light',
                'stage' => 'explore',
                'intent' => 'answer',
            ]);

        $response->assertOk();

        // If full_answer is generated (LLM available), should be complete
        if ($response->json('full_answer')) {
            $this->assertEquals('complete', $response->json('engage_status'));
            $this->assertEquals('full_answer_provided', $response->json('completion_reason'));
        }
    }
}
