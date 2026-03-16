<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\LessonStageContent;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LessonStageController extends Controller
{
    /**
     * Update the text content for a stage.
     */
    public function updateText(Request $request, Lesson $lesson, $stage)
    {
        $validated = $request->validate([
            'content' => 'nullable|string',
            'content_type' => 'sometimes|in:text,wysiwyg',
        ]);

        // Ensure stage is valid
        if (!in_array($stage, ['engage', 'explore', 'explain', 'elaborate', 'evaluate'])) {
            return response()->json(['error' => 'Invalid stage'], 422);
        }

        $contentType = $request->input('content_type', 'text');

        $stageContent = LessonStageContent::updateOrCreate(
            ['lesson_id' => $lesson->id, 'stage' => $stage],
            ['content' => $validated['content'] ?? '', 'content_type' => $contentType]
        );

        return response()->json([
            'success' => true,
            'message' => 'Stage content saved.',
            'data'    => $stageContent
        ]);
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
        if (!in_array($stage, ['engage', 'explore', 'explain', 'elaborate', 'evaluate'])) {
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
        // Store file
        $path = $file->store("lessons/{$lesson->id}/{$stage}", 'public');


        // Create media record
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

        // If it's a CSV for evaluate stage, import quiz questions
        if ($mediaType === 'csv' && $stage === 'evaluate') {
            try {
                $importCount = $this->importQuizQuestionsFromCsv($file->getRealPath(), $lesson->id);
                // Optionally delete previous questions? We'll replace them.
                // Already done inside import method.
            } catch (\Exception $e) {
                // If import fails, delete the uploaded file and media record? Or keep but report error.
                // We'll keep the file but return error.
                return response()->json([
                    'success' => false,
                    'message' => 'CSV upload failed: ' . $e->getMessage(),
                ], 422);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully.',
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
    public function destroyMedia(LessonMedia $media)
    {
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
        
    }
}