<?php

namespace App\Jobs;

use App\Models\LessonCheckpointCorpus;
use App\Models\LessonEmbedding;
use App\Services\OllamaEmbeddingGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\VectorStores\FileSystem\FileSystemVectorStore;
use Smalot\PdfParser\Parser as PdfParser;

class ProcessCheckpointCorpusEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;
    public int $backoff = 30;

    private const CHUNK_SIZE = 1200;
    private const CHUNK_OVERLAP = 150;
    private const BATCH_SIZE = 20;

    public function __construct(public LessonCheckpointCorpus $corpus)
    {
    }

    public function handle(): void
    {
        if ($this->corpus->processing_status === 'completed' && !empty($this->corpus->vector_store_path)) {
            return;
        }

        try {
            $this->corpus->update([
                'processing_status' => 'processing',
                'error_message' => null,
            ]);

            $text = $this->extractText();
            $chunks = $this->splitIntoChunks($text);
            $documents = $this->buildDocuments($chunks);
            $embeddedDocuments = $this->embedInBatches($documents);
            $vectorStoreFile = $this->persistToVectorStore($embeddedDocuments);
            $this->persistToDatabase($embeddedDocuments, $vectorStoreFile);

            $this->corpus->update([
                'processing_status' => 'completed',
                'vector_store_path' => $vectorStoreFile,
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($e);
            throw $e;
        }
    }

    private function extractText(): string
    {
        $path = Storage::disk('public')->path($this->corpus->file_path);

        if (!File::exists($path)) {
            throw new \RuntimeException('Checkpoint corpus file not found.');
        }

        $text = match ($this->corpus->file_type) {
            'pdf' => trim((new PdfParser())->parseFile($path)->getText()),
            'txt', 'md' => trim((string) File::get($path)),
            default => '',
        };

        if ($text === '') {
            throw new \RuntimeException('No text could be extracted from the checkpoint corpus file.');
        }

        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/(\r?\n){3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function splitIntoChunks(string $text): array
    {
        $chunks = [];
        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $end = $start + self::CHUNK_SIZE;

            if ($end >= $length) {
                $chunks[] = trim(mb_substr($text, $start));
                break;
            }

            $window = mb_substr($text, $start, self::CHUNK_SIZE);
            $cutAt = $this->findSentenceBoundary($window);

            if ($cutAt === null) {
                $cutAt = mb_strrpos($window, ' ') ?: self::CHUNK_SIZE;
            }

            $chunks[] = trim(mb_substr($window, 0, $cutAt));
            $start += max(1, $cutAt - self::CHUNK_OVERLAP);
        }

        return array_values(array_filter($chunks, fn (string $chunk) => mb_strlen($chunk) > 40));
    }

    private function findSentenceBoundary(string $window): ?int
    {
        $searchFrom = (int) (mb_strlen($window) * 0.75);
        $sub = mb_substr($window, $searchFrom);

        if (preg_match_all('/[.!?](?:\s|$)/u', $sub, $matches, PREG_OFFSET_CAPTURE)) {
            $last = end($matches[0]);
            return $searchFrom + $last[1] + 1;
        }

        return null;
    }

    private function buildDocuments(array $chunks): array
    {
        return array_map(function (string $chunk, int $index) {
            $doc = new Document();
            $doc->content = $chunk;
            $doc->sourceType = 'checkpoint_corpus';
            $doc->sourceName = $this->corpus->title ?: $this->corpus->file_name;
            $doc->formattedContent = json_encode([
                'lesson_id' => $this->corpus->lesson_id,
                'stage' => $this->corpus->stage,
                'corpus_id' => $this->corpus->id,
                'chunk_index' => $index,
            ]);

            return $doc;
        }, $chunks, array_keys($chunks));
    }

    private function embedInBatches(array $documents): array
    {
        $generator = new OllamaEmbeddingGenerator(
            model: config('ollama.embedding_model', 'embeddinggemma'),
            baseUrl: config('ollama.base_url', 'http://ollama:11434'),
        );

        $embedded = [];

        foreach (array_chunk($documents, self::BATCH_SIZE) as $batch) {
            $embedded = array_merge($embedded, $generator->embedDocuments($batch));
        }

        return $embedded;
    }

    private function persistToVectorStore(array $embeddedDocuments): string
    {
        $dir = storage_path('app/vector-store/checkpoints/lesson-' . $this->corpus->lesson_id . '/' . $this->corpus->stage);
        $file = $dir . '/corpus-' . $this->corpus->id . '.json';

        File::ensureDirectoryExists($dir, 0755);

        $store = new FileSystemVectorStore($file);
        $store->addDocuments($embeddedDocuments);

        return $file;
    }

    private function persistToDatabase(array $embeddedDocuments, string $vectorStoreFile): void
    {
        LessonEmbedding::query()
            ->where('source_type', 'checkpoint_corpus')
            ->where('source_id', (string) $this->corpus->id)
            ->delete();

        $now = now();
        $rows = array_map(fn (Document $doc, int $index) => [
            'lesson_id' => $this->corpus->lesson_id,
            'source_type' => 'checkpoint_corpus',
            'source_id' => (string) $this->corpus->id,
            'chunk_text' => $doc->content,
            'vector_path' => $vectorStoreFile,
            'chunk_index' => $index,
            'created_at' => $now,
            'updated_at' => $now,
        ], $embeddedDocuments, array_keys($embeddedDocuments));

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                LessonEmbedding::insert($chunk);
            }
        });
    }

    private function markFailed(\Throwable $e): void
    {
        $this->corpus->update([
            'processing_status' => 'failed',
            'error_message' => mb_substr($e->getMessage(), 0, 500),
        ]);

        Log::error('[Checkpoint Corpus] Embedding failed', [
            'corpus_id' => $this->corpus->id,
            'lesson_id' => $this->corpus->lesson_id,
            'stage' => $this->corpus->stage,
            'error' => $e->getMessage(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->markFailed($exception);
    }
}