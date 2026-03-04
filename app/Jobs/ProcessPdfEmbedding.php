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
use LLPhant\Embeddings\VectorStores\FileSystem\FileSystemVectorStore;
use LLPhant\Embeddings\Document;
use Smalot\PdfParser\Parser as PdfParser;

class ProcessPdfEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 600; // 10 minutes timeout for large PDFs


    /**
     * Create a new job instance.
     */
    public function __construct(public Lesson $lesson)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        try {
            // Update status to processing
            $this->lesson->update(['processing_status' => 'processing']);
            //check if the associated media is already embedded

            Log::info("Starting PDF embedding", [
                'lesson_id' => $this->lesson->id
            ]);

            // Get the full path to the PDF
            $fullPath = Storage::disk('public')->path($this->lesson->filepath);

            // Extract text from PDF
            $pdfParser = new PdfParser();
            $pdf = $pdfParser->parseFile($fullPath);
            $text = $pdf->getText();

            if (empty($text)) {
                throw new \Exception('No text could be extracted from PDF');
            }

            Log::info("Text extracted from PDF", [
                'media_id' => $this->lesson->id,
                'text_length' => strlen($text)
            ]);

            // Split text into chunks
            // Split text into chunks using a simple deterministic splitter
            // This avoids relying on a non-existent splitText() method.
            $maxChars = 800; // adjust as needed for chunk size
            $chunks = [];
            $textRemaining = trim($text);
            while ($textRemaining !== '') {
                if (mb_strlen($textRemaining) <= $maxChars) {
                    $chunks[] = $textRemaining;
                    break;
                }

                $segment = mb_substr($textRemaining, 0, $maxChars);
                // prefer cutting at the last whitespace to avoid breaking words
                $lastSpace = mb_strrpos($segment, ' ');
                if ($lastSpace !== false) {
                    $chunk = mb_substr($segment, 0, $lastSpace);
                    // remove the chunk and leading whitespace from the remaining text
                    $textRemaining = ltrim(mb_substr($textRemaining, $lastSpace));
                } else {
                    // no whitespace found in the segment, force cut
                    $chunk = $segment;
                    $textRemaining = mb_substr($textRemaining, $maxChars);
                }

                $chunks[] = $chunk;
            }

            // Create Document objects with metadata
            $documents = [];
            foreach ($chunks as $index => $chunk) {
                $doc = new Document();
                $doc->content = $chunk;
                $doc->sourceType = 'pdf';
                $doc->sourceName = $this->lesson->title;
                // Store metadata as part of content or use addMeta if available
                $documents[] = $doc;
            }
            Log::info("Documents split into chunks", [
                'media_id' => $this->lesson->id,
                'chunk_count' => count($documents)
            ]);

            // Generate embeddings using Ollama
            $embeddingGenerator = new OllamaEmbeddingGenerator(
                model: 'embeddinggemma', // or 'gemma:2b' if you prefer
                baseUrl: config('ollama.base_url', 'OLLAMA_URL=http://ollama:11434')
            );

            $embeddedDocuments = $embeddingGenerator->embedDocuments($documents);

            Log::info("Embeddings generated", [
                'knowledge_base_id' => $this->lesson->id,
                'embedded_count' => count($embeddedDocuments)
            ]);

            /// Create vector store directory if it doesn't exist
            $vectorStoreDir = storage_path('app/vector-store/course-' . $this->lesson->id);

            if (!File::exists($vectorStoreDir)) {
                File::makeDirectory($vectorStoreDir, 0755, true);
                Log::info("Created vector store directory", [
                    'path' => $vectorStoreDir
                ]);
            }

            $vectorStoreFile = $vectorStoreDir . '/knowledge-base-' . $this->lesson->id . '.json';

            // Store in vector database
            $vectorStore = new FileSystemVectorStore($vectorStoreFile);
            $vectorStore->addDocuments($embeddedDocuments);

            Log::info("Embeddings stored in vector database", [
                'knowledge_base_id' => $this->lesson->id,
                'file_path' => $vectorStoreFile
            ]);

            // Update status to completed
            $updated = $this->lesson->update([
                'processing_status' => 'completed',
                'vector_store_path' => $vectorStoreFile,
                //'processed_at' => now(),
                //'chunk_count' => count($documents),
                // 'vector_store_path' => $vectorStoreFile,
            ]);
            //store into lesson_embeddings table for easier querying later (optional, depends on how you want to implement retrieval)
            foreach ($embeddedDocuments as $index => $doc) {
                LessonEmbedding::create([
                    'id' => $this->lesson->id,
                    'source_type' => 'pdf',
                    'source_id' => $this->lesson->id,
                    'chunk_text' => $doc->content,
                    'vector_path' => $vectorStoreFile,
                    'chunk_index' => $index,
                ]);
            }

            if (!$updated) {
                Log::warning("Failed to update knowledge base", [
                    'knowledge_base_id' => $this->lesson->id
                ]);
            }

            // Verify the update
            $this->lesson->refresh();
            /*Log::info("Knowledge base updated", [
                'knowledge_base_id' => $this->lesson->id,
                'status' => $this->lesson->processing_status,
                'chunk_count' => $this->lesson->chunk_count,
                'vector_path' => $this->lesson->vector_store_path,
            ]);*/

            Log::info("PDF embedded successfully", [
                'knowledge_base_id' => $this->lesson->id,
                'chunks' => count($documents)
            ]);

        } catch (\Exception $e) {
            // Update status to failed
            $this->lesson->update([
                'processing_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("PDF embedding failed", [
                'knowledge_base_id' => $this->lesson->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->lesson->update([
            'processing_status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }

}
