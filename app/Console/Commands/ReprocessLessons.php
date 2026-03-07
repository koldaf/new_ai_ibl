<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Jobs\ProcessPdfEmbedding;
use Illuminate\Console\Command;

class ReprocessLessons extends Command
{
    protected $signature = 'lessons:reprocess {--failed : Only reprocess failed lessons} {--id= : Reprocess specific lesson ID}';
    protected $description = 'Reprocess PDF embeddings for lessons';

    public function handle()
    {
        $query = Lesson::query();

        if ($this->option('failed')) {
            $query->where('processing_status', 'failed');
        } elseif ($this->option('id')) {
            $query->where('id', $this->option('id'));
        }

        $lessons = $query->get();

        foreach ($lessons as $lesson) {
            $lesson->update(['processing_status' => 'pending']);
            ProcessPdfEmbedding::dispatch($lesson);
            $this->info("Queued lesson {$lesson->id} for reprocessing");
        }

        $this->info("Queued " . $lessons->count() . " lessons for reprocessing");
    }
}