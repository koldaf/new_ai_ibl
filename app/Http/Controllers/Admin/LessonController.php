<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    /**
     * Display a listing of lessons.
     */
    public function index()
    {
        $lessons = Lesson::latest()->paginate(15);
        return view('dashboard.admin.lessons.index', compact('lessons'));
    }

    /**
     * Show the form for creating a new lesson.
     */
    public function create()
    {
        return view('dashboard.admin.lessons.create');
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
            'description' => 'nullable|string',
        ]);

        $lesson = Lesson::create($validated);

        return redirect()->route('admin.lessons.edit', $lesson)
            ->with('success', 'Lesson created successfully. Now you can add content for each stage.');
    }

    /**
     * Show the form for editing the lesson (with 5E tabs).
     */
    public function edit(Lesson $lesson)
    {
        // Preload stage contents and media for each stage to use in the view
        $stages = ['engage', 'explore', 'explain', 'elaborate', 'evaluate'];
        $stageData = [];
        foreach ($stages as $stage) {
            $stageData[$stage] = [
                'content' => $lesson->getStageContent($stage),
                'media'   => $lesson->getStageMedia($stage),
            ];
        }

        return view('dashboard.admin.lessons.edit', compact('lesson', 'stageData', 'stages'));
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
        ]);

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