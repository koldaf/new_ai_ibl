<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCheckpointCorpusEmbedding;
use App\Models\EngageMcqQuestion;
use App\Models\Lesson;
use App\Models\LessonCheckpointCorpus;
use App\Models\LessonCheckpointQuestion;
use App\Models\LessonMedia;
use App\Models\LessonMisconception;
use App\Models\LessonStageContent;
use App\Models\LessonEmbedding;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LessonStageController extends Controller
{
    private const VALID_STAGES = ['engage', 'explore', 'explain', 'elaborate', 'evaluate'];
    private const CHECKPOINT_STAGES = ['explore', 'explain', 'elaborate'];

    /**
     * Update the text content for a stage.
     */
    public function updateText(Request $request, Lesson $lesson, $stage)
    {
        $validated = $request->validate([
            'content' => 'nullable|string',
            'content_type' => 'sometimes|in:text,wysiwyg',
            'activity_mode' => 'nullable|in:chat,mcq',
        ]);

        // Ensure stage is valid
        if (!$this->isValidStage($stage)) {
            return response()->json(['error' => 'Invalid stage'], 422);
        }

        $contentType = $request->input('content_type', 'text');

        $stageContent = LessonStageContent::updateOrCreate(
            ['lesson_id' => $lesson->id, 'stage' => $stage],
            [
                'content' => $validated['content'] ?? '',
                'content_type' => $contentType,
                'activity_mode' => $validated['activity_mode'] ?? 'chat',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Stage content saved.',
            'data'    => $stageContent
        ]);
    }

    public function upsertEngageMcq(Request $request, Lesson $lesson, string $stage)
    {
        if ($stage !== 'engage') {
            return response()->json(['error' => 'Engage MCQ is only available for the engage stage'], 422);
        }

        $validated = $request->validate([
            'question' => 'required|string',
            'option_a' => 'required|string|max:255',
            'option_b' => 'required|string|max:255',
            'option_c' => 'required|string|max:255',
            'option_d' => 'required|string|max:255',
            'correct_option' => ['required', Rule::in(['a', 'b', 'c', 'd'])],
            'feedback_option_a' => 'nullable|string',
            'feedback_option_b' => 'nullable|string',
            'feedback_option_c' => 'nullable|string',
            'feedback_option_d' => 'nullable|string',
        ]);

        LessonStageContent::updateOrCreate(
            ['lesson_id' => $lesson->id, 'stage' => $stage],
            ['activity_mode' => 'mcq']
        );

        $question = EngageMcqQuestion::updateOrCreate(
            ['lesson_id' => $lesson->id, 'stage' => $stage],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Engage MCQ saved.',
            'data' => $question,
        ]);
    }

    public function destroyEngageMcq(Lesson $lesson, string $stage)
    {
        if ($stage !== 'engage') {
            return response()->json(['error' => 'Engage MCQ is only available for the engage stage'], 422);
        }

        $question = EngageMcqQuestion::where('lesson_id', $lesson->id)
            ->where('stage', $stage)
            ->first();

        if (!$question) {
            return response()->json(['error' => 'Engage MCQ not found'], 404);
        }

        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Engage MCQ deleted.',
        ]);
    }

    public function storeCheckpointQuestion(Request $request, Lesson $lesson, string $stage)
    {
        if (!$this->isCheckpointStage($stage)) {
            return response()->json(['error' => 'Checkpoint questions are only available for explore, explain, and elaborate.'], 422);
        }

        $validated = $this->validateCheckpointQuestion($request);

        $question = LessonCheckpointQuestion::create([
            'lesson_id' => $lesson->id,
            'stage' => $stage,
            'question_text' => $validated['question_text'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? ($lesson->checkpointQuestions()->where('stage', $stage)->max('sort_order') + 1),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checkpoint question saved.',
            'data' => $this->serializeCheckpointQuestion($question),
        ]);
    }

    public function updateCheckpointQuestion(Request $request, Lesson $lesson, string $stage, LessonCheckpointQuestion $question)
    {
        if (!$this->isCheckpointStage($stage)) {
            return response()->json(['error' => 'Checkpoint questions are only available for explore, explain, and elaborate.'], 422);
        }

        if ($question->lesson_id !== $lesson->id || $question->stage !== $stage) {
            return response()->json(['error' => 'Checkpoint question not found for this lesson stage.'], 404);
        }

        $validated = $this->validateCheckpointQuestion($request);

        $question->update([
            'question_text' => $validated['question_text'],
            'is_active' => $validated['is_active'] ?? false,
            'sort_order' => $validated['sort_order'] ?? $question->sort_order,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checkpoint question updated.',
            'data' => $this->serializeCheckpointQuestion($question->fresh()),
        ]);
    }

    public function destroyCheckpointQuestion(Lesson $lesson, string $stage, LessonCheckpointQuestion $question)
    {
        if (!$this->isCheckpointStage($stage)) {
            return response()->json(['error' => 'Checkpoint questions are only available for explore, explain, and elaborate.'], 422);
        }

        if ($question->lesson_id !== $lesson->id || $question->stage !== $stage) {
            return response()->json(['error' => 'Checkpoint question not found for this lesson stage.'], 404);
        }

        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Checkpoint question deleted.',
        ]);
    }

    public function uploadCheckpointCorpus(Request $request, Lesson $lesson, string $stage)
    {
        if (!$this->isCheckpointStage($stage)) {
            return response()->json(['error' => 'Checkpoint corpus is only available for explore, explain, and elaborate.'], 422);
        }

        $validated = $request->validate([
            'file' => 'required|file|max:10240',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:1|max:9999',
        ]);

        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (!in_array($extension, ['pdf', 'txt', 'md'], true)) {
            return response()->json(['error' => 'Checkpoint corpus supports PDF, TXT, and MD files only.'], 422);
        }

        $path = null;

        try {
            $path = $file->store("lessons/{$lesson->id}/{$stage}/checkpoint-corpus", 'public');

            $corpus = LessonCheckpointCorpus::create([
                'lesson_id' => $lesson->id,
                'stage' => $stage,
                'title' => $validated['title'] ?? null,
                'description' => $validated['description'] ?? null,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $extension,
                'processing_status' => 'pending',
                'sort_order' => $validated['sort_order'] ?? ($lesson->checkpointCorpora()->where('stage', $stage)->max('sort_order') + 1),
                'created_by' => $request->user()?->id,
            ]);

            ProcessCheckpointCorpusEmbedding::dispatch($corpus);

            return response()->json([
                'success' => true,
                'message' => 'Checkpoint corpus uploaded. Embedding has started.',
                'data' => $this->serializeCheckpointCorpus($corpus->fresh()),
            ]);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'Checkpoint corpus upload failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function destroyCheckpointCorpus(Lesson $lesson, string $stage, LessonCheckpointCorpus $corpus)
    {
        if (!$this->isCheckpointStage($stage)) {
            return response()->json(['error' => 'Checkpoint corpus is only available for explore, explain, and elaborate.'], 422);
        }

        if ($corpus->lesson_id !== $lesson->id || $corpus->stage !== $stage) {
            return response()->json(['error' => 'Checkpoint corpus not found for this lesson stage.'], 404);
        }

        Storage::disk('public')->delete($corpus->file_path);

        if ($corpus->vector_store_path && File::exists($corpus->vector_store_path)) {
            File::delete($corpus->vector_store_path);
        }

        LessonEmbedding::query()
            ->where('source_type', 'checkpoint_corpus')
            ->where('source_id', (string) $corpus->id)
            ->delete();

        $corpus->delete();

        return response()->json([
            'success' => true,
            'message' => 'Checkpoint corpus deleted.',
        ]);
    }

    /**
     * Get the processing status of a checkpoint corpus (for polling).
     */
    public function getCheckpointCorpusStatus(Lesson $lesson, string $stage, LessonCheckpointCorpus $corpus)
    {
        if (!$this->isCheckpointStage($stage)) {
            return response()->json(['error' => 'Checkpoint corpus is only available for explore, explain, and elaborate.'], 422);
        }

        if ($corpus->lesson_id !== $lesson->id || $corpus->stage !== $stage) {
            return response()->json(['error' => 'Checkpoint corpus not found for this lesson stage.'], 404);
        }

        return response()->json([
            'id' => $corpus->id,
            'processing_status' => $corpus->processing_status,
            'error_message' => $corpus->error_message,
            'vector_store_path' => $corpus->vector_store_path,
        ]);
    }

    /**
     * Reprocess a failed checkpoint corpus embedding.
     */
    public function reprocessCheckpointCorpus(Lesson $lesson, string $stage, LessonCheckpointCorpus $corpus)
    {
        if (!$this->isCheckpointStage($stage)) {
            return response()->json(['error' => 'Checkpoint corpus is only available for explore, explain, and elaborate.'], 422);
        }

        if ($corpus->lesson_id !== $lesson->id || $corpus->stage !== $stage) {
            return response()->json(['error' => 'Checkpoint corpus not found for this lesson stage.'], 404);
        }

        if ($corpus->processing_status === 'pending' || $corpus->processing_status === 'processing') {
            return response()->json([
                'error' => 'Cannot reprocess a corpus that is already being processed.',
            ], 422);
        }

        try {
            // Clean up existing vector store and embeddings
            if ($corpus->vector_store_path && File::exists($corpus->vector_store_path)) {
                File::delete($corpus->vector_store_path);
            }

            LessonEmbedding::query()
                ->where('source_type', 'checkpoint_corpus')
                ->where('source_id', (string) $corpus->id)
                ->delete();

            // Reset status and dispatch job
            $corpus->update([
                'processing_status' => 'pending',
                'vector_store_path' => null,
                'error_message' => null,
            ]);

            ProcessCheckpointCorpusEmbedding::dispatch($corpus);

            return response()->json([
                'success' => true,
                'message' => 'Checkpoint corpus reprocessing started.',
                'data' => $this->serializeCheckpointCorpus($corpus->fresh()),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reprocess corpus: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Upload media for a stage.
     */
    public function uploadMedia(Request $request, Lesson $lesson, $stage)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'media_type' => ['required', Rule::in(['video', 'image', 'pdf', 'phet_html', 'csv'])],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        // Validate stage
        if (!$this->isValidStage($stage)) {
            return response()->json(['error' => 'Invalid stage'], 422);
        }

        $file = $request->file('file');
        $mediaType = $request->input('media_type');


        // Additional validation based on media type
        $allowedMimes = [
            'video' => ['mp4', 'avi', 'mov', 'wmv', 'flv'],
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'svg'],
            'pdf' => ['pdf'],
            'phet_html' => ['html', 'htm'],
            'csv' => ['csv', 'txt'], // allow txt for csv sometimes
        ];

        $extension = $file->getClientOriginalExtension();
        if (!in_array(strtolower($extension), $allowedMimes[$mediaType])) {
            return response()->json(['error' => 'Invalid file type for selected media category.'], 422);
        }

        $path = null;

        try {
            // Store the file first, then keep DB changes transactional.
            $path = $file->store("lessons/{$lesson->id}/{$stage}", 'public');

            DB::beginTransaction();

            $media = LessonMedia::create([
                'lesson_id'   => $lesson->id,
                'stage'       => $stage,
                'media_type'  => $mediaType,
                'file_path'   => $path,
                'file_name'   => $file->getClientOriginalName(),
                'title'       => $request->input('title'),
                'description' => $request->input('description'),
                'order'       => LessonMedia::where('lesson_id', $lesson->id)->where('stage', $stage)->count() + 1,
            ]);

            $message = 'File uploaded successfully.';

            if ($mediaType === 'csv' && $stage === 'evaluate') {
                $importCount = $this->importQuizQuestionsFromCsv($file->getRealPath(), $lesson->id);
                $message = "CSV uploaded successfully. Imported {$importCount} question(s).";
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($path) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => false,
                'error' => 'Upload failed: ' . $e->getMessage(),
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'media'   => [
                'id'         => $media->id,
                'url'        => $media->url,
                'file_name'  => $media->file_name,
                'media_type' => $media->media_type,
                'title'      => $media->title,
            ]
        ]);
    }

     /**
     * Import quiz questions from CSV file.
     * Replaces all existing questions for the lesson.
     *
     * @param string $filePath
     * @param int $lessonId
     * @return int Number of imported questions
     * @throws \Exception
     */
    protected function importQuizQuestionsFromCsv($filePath, $lessonId)
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception('Cannot open CSV file');
        }

        // Read header
        $header = fgetcsv($handle);
        $expectedHeader = ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option'];

        if ($header === false) {
            fclose($handle);
            throw new \Exception('CSV file is empty.');
        }

        $header = array_map(function ($value) {
            $value = (string) $value;
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

            return strtolower(trim($value));
        }, $header);

        if ($header !== $expectedHeader) {
            fclose($handle);
            throw new \Exception('CSV header does not match expected columns: ' . implode(',', $expectedHeader));
        }

        // Delete existing questions for this lesson (replace)
        QuizQuestion::where('lesson_id', $lessonId)->delete();

        $imported = 0;
        while (($row = fgetcsv($handle)) !== false) {
            // Skip rows that don't have exactly 6 columns
            if (count($row) !== 6) {
                continue;
            }

            $correct = strtolower(trim($row[5]));
            if (!in_array($correct, ['a', 'b', 'c', 'd'])) {
                // Invalid correct option, skip this row
                continue;
            }

            QuizQuestion::create([
                'lesson_id'      => $lessonId,
                'question'       => trim($row[0]),
                'option_a'       => trim($row[1]),
                'option_b'       => trim($row[2]),
                'option_c'       => trim($row[3]),
                'option_d'       => trim($row[4]),
                'correct_option' => $correct,
            ]);

            $imported++;
        }

        fclose($handle);

        if ($imported === 0) {
            throw new \Exception('No valid questions found in CSV.');
        }

        return $imported;
    }

    /**
     * Delete a media file.
     */
    public function destroyMedia(Lesson $lesson, string $stage, $media)
    {
        if (!$this->isValidStage($stage)) {
            return response()->json(['error' => 'Invalid stage'], 422);
        }

        $media = LessonMedia::whereKey($media)
            ->where('lesson_id', $lesson->id)
            ->where('stage', $stage)
            ->first();

        if (!$media) {
            return response()->json(['error' => 'Media not found for this lesson stage.'], 404);
        }

        // Delete file from storage
        Storage::disk('public')->delete($media->file_path);

        // Delete record
        $media->delete();

        return response()->json([
            'success' => true,
            'message' => 'Media deleted successfully.'
        ]);
    }

    //Misconception
    public function storeMisconception(Request $request, Lesson $lesson, $stage)
    {
        if (!$this->isValidStage($stage)) {
            return response()->json(['error' => 'Invalid stage'], 422);
        }

        $validated = $this->validateMisconception($request);

        $misconception = LessonMisconception::create([
            'lesson_id' => $lesson->id,
            'stage' => $stage,
            'concept_tag' => $validated['concept_tag'] ?? null,
            'label' => $validated['label'],
            'description' => $validated['description'] ?? null,
            'correct_concept' => $validated['correct_concept'] ?? null,
            'remediation_hint' => $validated['remediation_hint'] ?? null,
            'source' => 'template',
            'status' => $validated['status'] ?? 'approved',
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Misconception template saved.',
            'data' => $misconception,
        ]);
    }

    public function updateMisconception(Request $request, Lesson $lesson, $stage, LessonMisconception $misconception)
    {
        if (!$this->isValidStage($stage)) {
            return response()->json(['error' => 'Invalid stage'], 422);
        }

        if ($misconception->lesson_id !== $lesson->id || $misconception->stage !== $stage) {
            return response()->json(['error' => 'Misconception not found for this lesson stage'], 404);
        }

        $validated = $this->validateMisconception($request);

        $misconception->update([
            'concept_tag' => $validated['concept_tag'] ?? null,
            'label' => $validated['label'],
            'description' => $validated['description'] ?? null,
            'correct_concept' => $validated['correct_concept'] ?? null,
            'remediation_hint' => $validated['remediation_hint'] ?? null,
            'status' => $validated['status'] ?? $misconception->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Misconception template updated.',
            'data' => $misconception->fresh(),
        ]);
    }

    public function destroyMisconception(Lesson $lesson, $stage, LessonMisconception $misconception)
    {
        if (!$this->isValidStage($stage)) {
            return response()->json(['error' => 'Invalid stage'], 422);
        }

        if ($misconception->lesson_id !== $lesson->id || $misconception->stage !== $stage) {
            return response()->json(['error' => 'Misconception not found for this lesson stage'], 404);
        }

        $misconception->delete();

        return response()->json([
            'success' => true,
            'message' => 'Misconception template deleted.',
        ]);
    }

    private function validateMisconception(Request $request): array
    {
        return $request->validate([
            'concept_tag' => 'nullable|string|max:100',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'correct_concept' => 'nullable|string',
            'remediation_hint' => 'nullable|string',
            'status' => ['nullable', Rule::in(['pending_review', 'approved', 'rejected'])],
        ]);
    }

    private function validateCheckpointQuestion(Request $request): array
    {
        return $request->validate([
            'question_text' => 'required|string|max:1000',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:1|max:9999',
        ]);
    }

    private function isCheckpointStage(string $stage): bool
    {
        return in_array($stage, self::CHECKPOINT_STAGES, true);
    }

    private function serializeCheckpointQuestion(LessonCheckpointQuestion $question): array
    {
        return [
            'id' => $question->id,
            'question_text' => $question->question_text,
            'is_active' => (bool) $question->is_active,
            'sort_order' => $question->sort_order,
        ];
    }

    private function serializeCheckpointCorpus(LessonCheckpointCorpus $corpus): array
    {
        return [
            'id' => $corpus->id,
            'title' => $corpus->title,
            'description' => $corpus->description,
            'file_name' => $corpus->file_name,
            'file_type' => $corpus->file_type,
            'processing_status' => $corpus->processing_status,
            'sort_order' => $corpus->sort_order,
            'error_message' => $corpus->error_message,
            'url' => $corpus->url,
        ];
    }

    private function isValidStage(string $stage): bool
    {
        return in_array($stage, self::VALID_STAGES, true);
    }
}