<?php

namespace App\Jobs;

use App\Models\LessonEmbedding;
use App\Models\Lesson;
use App\Services\OllamaEmbeddingGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use LLPhant\Embeddings\VectorStores\FileSystem\FileSystemVectorStore;
use LLPhant\Embeddings\Document;
use Smalot\PdfParser\Parser as PdfParser;

class ProcessPdfEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job configuration
     */
    public int $timeout      = 600;   // 10 min for large PDFs
    public int $tries        = 3;     // retry up to 3 times on transient failures
    public int $backoff      = 30;    // wait 30s between retries

    /**
     * Chunking configuration — tuned for offline embedding models.
     * 1 500 chars ≈ ~300 tokens; good balance between context and latency.
     */
    private const CHUNK_SIZE    = 1500; // characters per chunk
    private const CHUNK_OVERLAP = 200;  // characters of overlap between chunks
    private const BATCH_SIZE    = 20;   // documents sent to Ollama per batch

    public function __construct(public Lesson $lesson) {}

    // -------------------------------------------------------------------------
    // Main handler
    // -------------------------------------------------------------------------

    public function handle(): void
    {
        // --- 1. Idempotency guard: skip if already successfully processed ----
        if ($this->lesson->processing_status === 'completed') {
            Log::info('[RAG] Lesson already embedded — skipping.', [
                'lesson_id' => $this->lesson->id,
            ]);
            return;
        }

        try {
            $this->lesson->update(['processing_status' => 'processing']);

            Log::info('[RAG] Starting PDF embedding', ['lesson_id' => $this->lesson->id]);

            // --- 2. Validate PDF path ------------------------------------------
            $pdfPath = $this->resolvePdfPath();

            // --- 3. Extract text from PDF --------------------------------------
            $text = $this->extractText($pdfPath);

            // --- 4. Split into overlapping chunks ------------------------------
            $chunks = $this->splitIntoChunks($text);

            Log::info('[RAG] Text chunked', [
                'lesson_id'   => $this->lesson->id,
                'chunk_count' => count($chunks),
            ]);

            // --- 5. Build Document objects -------------------------------------
            $documents = $this->buildDocuments($chunks);

            // --- 6. Generate embeddings in batches (avoids Ollama OOM) ---------
            $embeddedDocuments = $this->embedInBatches($documents);

            Log::info('[RAG] Embeddings generated', [
                'lesson_id'      => $this->lesson->id,
                'embedded_count' => count($embeddedDocuments),
            ]);

            // --- 7. Persist to vector store (filesystem JSON) ------------------
            $vectorStoreFile = $this->persistToVectorStore($embeddedDocuments);

            // --- 8. Persist to DB in a single transaction ----------------------
            $this->persistToDatabase($embeddedDocuments, $vectorStoreFile);

            // --- 9. Mark lesson as complete ------------------------------------
            $this->lesson->update([
                'processing_status' => 'completed',
                'vector_store_path' => $vectorStoreFile,
            ]);

            Log::info('[RAG] PDF embedded successfully', [
                'lesson_id'   => $this->lesson->id,
                'chunk_count' => count($embeddedDocuments),
            ]);

        } catch (\Throwable $e) {
            $this->markFailed($e);
            throw $e; // re-throw so the queue marks this attempt as failed
        }
    }

    // -------------------------------------------------------------------------
    // Step helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve and validate the full path to the lesson PDF.
     */
    private function resolvePdfPath(): string
    {
        if (empty($this->lesson->lesson_material_file)) {
            throw new \RuntimeException('Lesson material file path is not set.');
        }

        $path = Storage::disk('public')->path($this->lesson->lesson_material_file);

        if (!File::exists($path)) {
            throw new \RuntimeException("PDF file not found at path: {$path}");
        }

        return $path;
    }

    /**
     * Parse the PDF and extract raw text.
     */
    private function extractText(string $pdfPath): string
    {
        $parser = new PdfParser();
        $text   = trim($parser->parseFile($pdfPath)->getText());

        if (empty($text)) {
            throw new \RuntimeException('No text could be extracted from the PDF.');
        }

        // Normalise whitespace: collapse multiple blank lines, strip non-printable chars
        $text = preg_replace('/[ \t]+/', ' ', $text);           // collapse horizontal space
        $text = preg_replace('/(\r?\n){3,}/', "\n\n", $text);   // max 2 blank lines
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\xFF]/', '', $text); // drop junk

        Log::info('[RAG] Text extracted', [
            'lesson_id'   => $this->lesson->id,
            'text_length' => mb_strlen($text),
        ]);

        return $text;
    }

    /**
     * Split text into overlapping chunks, preferring sentence / word boundaries.
     *
     * Overlap ensures that context is not lost when a relevant passage straddles
     * two chunk boundaries — critical for retrieval quality in offline RAG.
     */
    private function splitIntoChunks(string $text): array
    {
        $chunks    = [];
        $length    = mb_strlen($text);
        $start     = 0;

        while ($start < $length) {
            $end = $start + self::CHUNK_SIZE;

            if ($end >= $length) {
                // Last chunk — take whatever is left
                $chunks[] = trim(mb_substr($text, $start));
                break;
            }

            // Prefer cutting at sentence boundary (. ! ?) within the last 20% of the chunk
            $window    = mb_substr($text, $start, self::CHUNK_SIZE);
            $cutAt     = $this->findSentenceBoundary($window);

            if ($cutAt === null) {
                // Fall back to last whitespace
                $cutAt = mb_strrpos($window, ' ') ?: self::CHUNK_SIZE;
            }

            $chunks[] = trim(mb_substr($window, 0, $cutAt));

            // Move start forward, backing up by OVERLAP to preserve context
            $start += $cutAt - self::CHUNK_OVERLAP;
        }

        return array_filter($chunks, fn(string $c) => mb_strlen(trim($c)) > 50);
    }

    /**
     * Find the last sentence-ending position in the final 20% of a text window.
     * Returns null if none found.
     */
    private function findSentenceBoundary(string $window): ?int
    {
        $searchFrom = (int) (mb_strlen($window) * 0.80);
        $sub        = mb_substr($window, $searchFrom);

        // Match '. ', '! ', '? ', or end-of-string after punctuation
        if (preg_match_all('/[.!?](?:\s|$)/u', $sub, $matches, PREG_OFFSET_CAPTURE)) {
            $last = end($matches[0]);
            return $searchFrom + $last[1] + 1; // +1 to include the punctuation
        }

        return null;
    }

    /**
     * Wrap plain strings in LLPhant Document objects with lesson metadata.
     *
     * @param  string[]  $chunks
     * @return Document[]
     */
    private function buildDocuments(array $chunks): array
    {
        return array_map(function (string $chunk, int $index) {
            $doc             = new Document();
            $doc->content    = $chunk;
            $doc->sourceType = 'pdf';
            $doc->sourceName = $this->lesson->title ?? "lesson-{$this->lesson->id}";
            // Store index in formattedContent so it survives serialisation
            $doc->formattedContent = json_encode([
                'chunk_index' => $index,
                'lesson_id'   => $this->lesson->id,
            ]);
            return $doc;
        }, $chunks, array_keys($chunks));
    }

    /**
     * Send documents to Ollama in small batches to avoid memory spikes.
     * Offline models can be memory-constrained; batching keeps the process stable.
     *
     * @param  Document[]  $documents
     * @return Document[]
     */
    private function embedInBatches(array $documents): array
    {
        $generator = new OllamaEmbeddingGenerator(
            model:   config('ollama.embedding_model', 'embeddinggemma'),
            baseUrl: config('ollama.base_url', 'http://ollama:11434'),
        );

        $embedded = [];
        $batches  = array_chunk($documents, self::BATCH_SIZE);

        foreach ($batches as $batchIndex => $batch) {
            Log::debug('[RAG] Embedding batch', [
                'lesson_id'   => $this->lesson->id,
                'batch'       => $batchIndex + 1,
                'total_batches' => count($batches),
            ]);

            $embedded = array_merge($embedded, $generator->embedDocuments($batch));
        }

        return $embedded;
    }

    /**
     * Write embedded documents to the filesystem vector store.
     * Returns the absolute path to the store file.
     *
     * @param  Document[]  $embeddedDocuments
     */
    private function persistToVectorStore(array $embeddedDocuments): string
    {
        $dir  = storage_path('app/vector-store/course-' . $this->lesson->id);
        $file = $dir . '/lesson-' . $this->lesson->id . '.json';

        File::ensureDirectoryExists($dir, 0755);

        $store = new FileSystemVectorStore($file);
        $store->addDocuments($embeddedDocuments);

        Log::info('[RAG] Vector store written', ['path' => $file]);

        return $file;
    }

    /**
     * Bulk-insert all embedding rows inside a single DB transaction.
     * Using chunked inserts (500 rows each) keeps individual queries small.
     *
     * @param  Document[]  $embeddedDocuments
     */
    private function persistToDatabase(array $embeddedDocuments, string $vectorStoreFile): void
    {
        // Remove any stale rows for this lesson before re-inserting
        LessonEmbedding::where('source_id', $this->lesson->id)->delete();

        $now  = now();
        $rows = array_map(fn(Document $doc, int $index) => [
            'lesson_id'   => $this->lesson->id,            // required by migration
            'source_type' => 'pdf',
            'source_id'   => $this->lesson->id,
            'chunk_text'  => $doc->content,
            'vector_path' => $vectorStoreFile,
            'chunk_index' => $index,
            'created_at'  => $now,
            'updated_at'  => $now,
        ], $embeddedDocuments, array_keys($embeddedDocuments));

        DB::transaction(function () use ($rows) {
            // Insert in chunks of 500 to avoid hitting DB packet-size limits
            foreach (array_chunk($rows, 500) as $chunk) {
                LessonEmbedding::insert($chunk);
            }
        });

        Log::info('[RAG] Embeddings persisted to DB', [
            'lesson_id' => $this->lesson->id,
            'row_count' => count($rows),
        ]);
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    private function markFailed(\Throwable $e): void
    {
        $this->lesson->update([
            'processing_status' => 'failed',
            'error_message'     => mb_substr($e->getMessage(), 0, 500),
        ]);

        Log::error('[RAG] PDF embedding failed', [
            'lesson_id' => $this->lesson->id,
            'error'     => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
        ]);
    }

    /**
     * Called by Laravel after all retry attempts are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $this->markFailed($exception);
    }
}