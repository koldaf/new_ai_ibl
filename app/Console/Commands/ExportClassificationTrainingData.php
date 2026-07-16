<?php

namespace App\Console\Commands;

use App\Models\AiChatMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Exports admin-reviewed classifications (see ClassificationReviewController) into a
 * JSONL supervised fine-tuning dataset. Only human-verified rows are included —
 * training on the model's own unverified past outputs would just reinforce its
 * existing mistakes, which is the opposite of the point.
 *
 * The prompt template here approximates (does not byte-for-byte replicate) the real
 * prompts in EngageDecisionService/StageCheckpointService. If those change materially,
 * update this template to match, since fine-tuning on a mismatched prompt shape won't
 * transfer well to the real inference-time prompt.
 */
class ExportClassificationTrainingData extends Command
{
    protected $signature = 'ai:export-classification-training-data
        {--output=storage/app/finetune/classification-dataset.jsonl : Output JSONL file path}
        {--min-examples=50 : Warn if the exported dataset has fewer than this many rows}';

    protected $description = 'Export admin-verified AI classifications into a JSONL dataset for fine-tuning the classification model';

    private const CLASSIFICATION_VALUES = ['correct', 'partial', 'misconception', 'off_topic'];

    private const FALLBACK_FEEDBACK = [
        'correct' => 'Good, that correctly reflects the concept.',
        'partial' => 'Partly right, but a key idea is still missing.',
        'misconception' => 'Not quite - this reflects a misconception worth revisiting.',
        'off_topic' => 'That does not address the question yet.',
    ];

    public function handle(): int
    {
        $outputPath = base_path($this->option('output'));
        $minExamples = (int) $this->option('min-examples');

        $messages = AiChatMessage::query()
            ->whereNotNull('reviewed_at')
            ->whereNotNull('review_verdict')
            ->whereIn('classification', self::CLASSIFICATION_VALUES)
            ->with('lesson')
            ->orderBy('id')
            ->get();

        if ($messages->isEmpty()) {
            $this->error('No reviewed classifications found. Use the admin "Review AI Grading" page to verify some first.');
            return self::FAILURE;
        }

        @mkdir(dirname($outputPath), 0755, true);
        $handle = fopen($outputPath, 'w');

        $written = 0;
        foreach ($messages as $message) {
            $example = $this->buildExample($message);
            if ($example === null) {
                continue;
            }

            fwrite($handle, json_encode($example, JSON_UNESCAPED_SLASHES) . "\n");
            $written++;
        }

        fclose($handle);

        if ($written === 0) {
            @unlink($outputPath);
            $this->error('None of the reviewed messages had usable lesson stage content to build a training prompt from — nothing written.');
            return self::FAILURE;
        }

        $this->info("Wrote {$written} training examples to {$outputPath}");

        if ($written < $minExamples) {
            $this->warn(
                "That's below the suggested minimum of {$minExamples} examples. Fine-tuning on this few examples " .
                'risks overfitting to specific phrasing rather than learning the general judgment. Keep reviewing ' .
                'more classifications on the admin "Review AI Grading" page before training.'
            );
        }

        return self::SUCCESS;
    }

    private function buildExample(AiChatMessage $message): ?array
    {
        $lesson = $message->lesson;
        if ($lesson === null) {
            return null;
        }

        $stageContent = optional($lesson->getStageContent($message->stage))->content;
        $context = trim(strip_tags((string) $stageContent));
        if ($context === '') {
            return null;
        }

        [$classification, $confidence, $feedback, $followUp] = $this->resolveTarget($message);

        $system = 'You are an educational assistant evaluating a student response. Return only valid JSON. No markdown, no explanation.';

        $prompt = "Classify the student answer using the lesson context.\n\n" .
            'Allowed classification values: ' . implode(', ', self::CLASSIFICATION_VALUES) . ".\n" .
            "Confidence must be a number between 0 and 1.\n" .
            "Feedback should be one concise sentence.\n" .
            "Follow-up should be null when classification is correct; otherwise ask one short coaching question.\n\n" .
            "Return exactly this JSON shape:\n" .
            "{\"classification\":\"" . implode('|', self::CLASSIFICATION_VALUES) . "\",\"confidence\":0.0,\"feedback\":\"...\",\"follow_up\":\"... or null\"}\n\n" .
            "Lesson context:\n<CONTEXT>\n{$context}\n</CONTEXT>\n\n" .
            "Student answer: {$message->answer}";

        $completion = json_encode([
            'classification' => $classification,
            'confidence' => $confidence,
            'feedback' => $feedback,
            'follow_up' => $followUp,
        ], JSON_UNESCAPED_SLASHES);

        return [
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
                ['role' => 'assistant', 'content' => $completion],
            ],
        ];
    }

    /**
     * When the admin confirmed the AI's own verdict, that verdict (with its original
     * feedback/follow-up) is the training target. When the admin corrected it, we only
     * captured a corrected *classification* (see ClassificationReviewController) — not
     * a rewritten feedback sentence — so feedback falls back to the reviewer's note if
     * they left one, otherwise a generic per-class sentence. This is a real gap: the
     * review UI doesn't yet collect verified feedback text, only a verified label.
     */
    private function resolveTarget(AiChatMessage $message): array
    {
        if ($message->review_verdict === 'correct') {
            return [
                $message->classification,
                $message->confidence ?? 0.75,
                $message->feedback_text ?: self::FALLBACK_FEEDBACK[$message->classification],
                $message->follow_up_question ?: null,
            ];
        }

        $corrected = $message->corrected_classification;
        $feedback = $message->review_notes ?: self::FALLBACK_FEEDBACK[$corrected];

        return [$corrected, 0.85, Str::limit($feedback, 200, ''), null];
    }
}
