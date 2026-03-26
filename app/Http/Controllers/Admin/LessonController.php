<?php

namespace App\Http\Controllers\Admin;
use App\Jobs\ProcessPdfEmbedding;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    /**
     * Display a listing of lessons.
     */
    public function index()
    {
        $lessons = Lesson::with('teacher')->latest()->paginate(15);
        return view('dashboard.admin.lessons.index', compact('lessons'));
    }

    /**
     * Show the form for creating a new lesson.
     */
    public function create()
    {
        $teachers = User::query()->where('role', 'teacher')->orderBy('name')->get();

        return view('dashboard.admin.lessons.create', compact('teachers'));
    }

    /**
     * Store a newly created lesson in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'subject'     => 'nullable|string|max:255',
            'grade_level' => 'nullable|string|max:50',
            'file' => 'required|file|mimes:pdf|max:5120',
            'description' => 'nullable|string',
            'teacher_id' => 'nullable|exists:users,id',
        ]);

        if (!empty($validated['teacher_id'])) {
            abort_unless(User::whereKey($validated['teacher_id'])->where('role', 'teacher')->exists(), 422);
        }

        $file = $request->file('file');
        //$path = $file->store("lessons/{$lesson->id}/{$stage}", 'public');
        $path = $file->store('lessons/' . uniqid(), 'public');

        //$lesson = Lesson::create($validated);
        $lesson = Lesson::create([
            'title' => $validated['title'],
            'teacher_id' => $validated['teacher_id'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'grade_level' => $validated['grade_level'] ?? null,
            'description' => $validated['description'] ?? null,
            'lesson_material_file' => $path,
            'processing_status' => 'pending',
        ]);


        //dispatch job to process PDF and create embeddings
        ProcessPdfEmbedding::dispatch($lesson);

        return redirect()->route('admin.lessons.edit', $lesson)
            ->with('success', 'Lesson created successfully. Now you can add content for each stage.');
    }

    /**
     * Show the form for editing the lesson (with 5E tabs).
     */
    public function edit(Lesson $lesson)
    {
        $teachers = User::query()->where('role', 'teacher')->orderBy('name')->get();

        // Preload stage contents and media for each stage to use in the view
        $stages = ['engage', 'explore', 'explain', 'elaborate', 'evaluate'];
        $stageData = [];
        foreach ($stages as $stage) {
            $stageData[$stage] = [
                'content' => $lesson->getStageContent($stage),
                'media'   => $lesson->getStageMedia($stage),
                'misconceptions' => $lesson->misconceptions()->where('stage', $stage)->latest()->get(),
                'engageMcq' => $stage === 'engage' ? $lesson->getEngageMcqQuestion($stage) : null,
                'checkpointQuestions' => in_array($stage, ['explore', 'explain', 'elaborate'], true)
                    ? $lesson->getCheckpointQuestions($stage)
                    : collect(),
                'checkpointCorpora' => in_array($stage, ['explore', 'explain', 'elaborate'], true)
                    ? $lesson->getCheckpointCorpora($stage)
                    : collect(),
            ];
        }

        return view('dashboard.admin.lessons.edit', compact('lesson', 'stageData', 'stages', 'teachers'));
    }

    /**
     * Update the lesson basic information.
     */
    public function update(Request $request, Lesson $lesson)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'subject'     => 'nullable|string|max:255',
            'grade_level' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'teacher_id' => 'nullable|exists:users,id',
        ]);

        if (!empty($validated['teacher_id'])) {
            abort_unless(User::whereKey($validated['teacher_id'])->where('role', 'teacher')->exists(), 422);
        }

        $lesson->update($validated);

        return redirect()->route('admin.lessons.edit', $lesson)
            ->with('success', 'Lesson updated successfully.');
    }

    /**
     * Remove the specified lesson.
     */
    public function destroy(Lesson $lesson)
    {
        // Media files will be deleted automatically by model event? We'll handle in LessonMedia observer later.
        $lesson->delete();

        return redirect()->route('admin.lessons.index')
            ->with('success', 'Lesson deleted successfully.');
    }
}