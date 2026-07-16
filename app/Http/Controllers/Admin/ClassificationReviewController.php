<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiChatMessage;
use Illuminate\Http\Request;

class ClassificationReviewController extends Controller
{
    private const CLASSIFICATION_VALUES = ['correct', 'partial', 'misconception', 'off_topic'];

    public function index(Request $request)
    {
        $filter = $request->get('filter', 'unreviewed');

        $query = AiChatMessage::query()
            ->whereNotNull('classification')
            ->whereIn('classification', self::CLASSIFICATION_VALUES)
            ->with(['user:id,name', 'lesson:id,title'])
            ->latest('id');

        if ($filter === 'reviewed') {
            $query->whereNotNull('reviewed_at');
        } else {
            $query->whereNull('reviewed_at');
        }

        $messages = $query->paginate(20)->withQueryString();

        $totalClassified = AiChatMessage::query()
            ->whereNotNull('classification')
            ->whereIn('classification', self::CLASSIFICATION_VALUES)
            ->count();

        $reviewedCount = AiChatMessage::query()->whereNotNull('reviewed_at')->count();
        $disagreeCount = AiChatMessage::query()->where('review_verdict', 'incorrect')->count();

        return view('dashboard.admin.classification_reviews.index', [
            'messages' => $messages,
            'filter' => $filter,
            'totalClassified' => $totalClassified,
            'reviewedCount' => $reviewedCount,
            'needsReviewCount' => $totalClassified - $reviewedCount,
            'disagreeCount' => $disagreeCount,
            'classificationValues' => self::CLASSIFICATION_VALUES,
        ]);
    }

    public function review(AiChatMessage $message, Request $request)
    {
        $validated = $request->validate([
            'verdict' => 'required|in:correct,incorrect',
            'corrected_classification' => 'nullable|in:' . implode(',', self::CLASSIFICATION_VALUES),
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validated['verdict'] === 'incorrect' && empty($validated['corrected_classification'])) {
            return back()->withErrors([
                'corrected_classification' => 'Pick what the classification should have been.',
            ]);
        }

        $message->update([
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'review_verdict' => $validated['verdict'],
            'corrected_classification' => $validated['verdict'] === 'incorrect' ? $validated['corrected_classification'] : null,
            'review_notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Review saved.');
    }
}
