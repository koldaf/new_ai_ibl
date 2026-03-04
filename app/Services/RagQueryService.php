<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonEmbedding;
use App\Services\OllamaEmbeddingGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LLPhant\Embeddings\VectorStores\FileSystem\FileSystemVectorStore;
use LLPhant\Embeddings\Document;

class RagQueryService
{

    private OllamaEmbeddingGenerator $embeddingGenerator;
    private string $llmModel;
    private string $ollamaUrl;

    public function __construct()
    {
        $this->embeddingGenerator = new OllamaEmbeddingGenerator(
            model: config('ollama.embedding_model', 'nomic-embed-text'),
            baseUrl: config('ollama.base_url', 'http://ollama:11434')
        );

        $this->llmModel = config('ollama.llm_model', 'qwen3:0.6b');
        $this->ollamaUrl = config('ollama.base_url', 'http://ollama:11434');
    }

    /**
     * Process a query and return answer with sources (non-streaming)
     */
     /**
     * Embed all content for a lesson.
     */
    public function embedLesson(Lesson $lesson, array $options = []): void
    {
        // Delete existing embeddings for this lesson
        LessonEmbedding::where('lesson_id', $lesson->id)->delete();

        $documents = [];

        // 1. Stage contents (text)
        $stages = ['engage', 'explore', 'explain', 'elaborate', 'evaluate'];
        foreach ($stages as $stage) {
            $stageContent = $lesson->getStageContent($stage);
            if ($stageContent && !empty($stageContent->content)) {
                // Split into chunks
                $stageDocs = $this->createDocumentsFromText(
                    $stageContent->content,
                    $lesson->id,
                    "{$stage}_text",
                    $stageContent->id
                );
                $documents = array_merge($documents, $stageDocs);
            }
        }

        // 2. Media files (text extraction from PDFs etc.)
        $mediaFiles = $lesson->media()->whereIn('media_type', ['pdf', 'csv', 'phet_html'])->get();
        foreach ($mediaFiles as $media) {
            // Extract text based on file type
            $filePath = storage_path("app/public/{$media->file_path}");
            if (file_exists($filePath)) {
                $text = $this->extractTextFromFile($filePath, $media->media_type);
                if ($text) {
                    $mediaDocs = $this->createDocumentsFromText(
                        $text,
                        $lesson->id,
                        "{$media->stage}_media",
                        $media->id
                    );
                    $documents = array_merge($documents, $mediaDocs);
                }
            }
        }

        // 3. Quiz questions (if any)
        $quizQuestions = $lesson->quizQuestions;
        if ($quizQuestions->count()) {
            $quizText = '';
            foreach ($quizQuestions as $q) {
                $quizText .= "Question: {$q->question}\nOptions: A) {$q->option_a} B) {$q->option_b} C) {$q->option_c} D) {$q->option_d}\nCorrect: {$q->correct_option}\n\n";
            }
            $quizDocs = $this->createDocumentsFromText($quizText, $lesson->id, 'quiz', null);
            $documents = array_merge($documents, $quizDocs);
        }

        // 4. AI Setup PDFs (misconceptions) - we need to store those PDFs first. They are uploaded in AI Setup tab.
        // We'll handle that separately.

        if (empty($documents)) {
            return;
        }

        // Generate embeddings and store
        $this->embedAndStoreDocuments($documents);
    }
     /**
     * Create Document objects from text with chunking.
     */
    protected function createDocumentsFromText(string $text, int $lessonId, string $sourceType, $sourceId = null, int $chunkIndexBase = 0): array
    {
        // Use LLPhant's DocumentSplitter to split into chunks
        $document = new Document();
        $document->content = $text;
        $document->sourceType = $sourceType;
        $document->sourceId = $sourceId;
        $document->lessonId = $lessonId;

        $splitter = DocumentSplitter::splitDocument($document, 500); // chunk size 500 characters, adjust

        $documents = [];
        foreach ($splitter as $i => $chunkDoc) {
            $chunkDoc->metadata = [
                'lesson_id' => $lessonId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'chunk_index' => $chunkIndexBase + $i,
            ];
            $documents[] = $chunkDoc;
        }
        return $documents;
    }

    /**
     * Generate embeddings for documents and store in DB.
     */
    protected function embedAndStoreDocuments(array $documents): void
    {
        // Generate embeddings for all documents (batch)
        $chunks = array_chunk($documents, 10); // process in batches of 10
        foreach ($chunks as $chunk) {
            $contents = array_map(fn($doc) => $doc->content, $chunk);
            $embeddings = $this->embeddingGenerator->embedTexts($contents);
            foreach ($chunk as $idx => $doc) {
                LessonEmbedding::create([
                    'lesson_id' => $doc->metadata['lesson_id'],
                    'source_type' => $doc->metadata['source_type'],
                    'source_id' => $doc->metadata['source_id'],
                    'chunk_text' => $doc->content,
                    'embedding' => $embeddings[$idx],
                    'chunk_index' => $doc->metadata['chunk_index'],
                ]);
            }
        }
    }

    /**
     * Extract text from file based on type.
     */
    protected function extractTextFromFile(string $filePath, string $type): ?string
    {
        // Implement extraction logic for PDF, CSV, HTML (PhET)
        switch ($type) {
            case 'pdf':
                // Use a PDF parser like smalot/pdfparser or spatie/pdf-to-text
                // Assuming we have installed a package
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                return $pdf->getText();
            case 'csv':
                // Read CSV and convert to text
                $rows = array_map('str_getcsv', file($filePath));
                $header = array_shift($rows);
                $text = '';
                foreach ($rows as $row) {
                    $text .= implode(' | ', $row) . "\n";
                }
                return $text;
            case 'phet_html':
                // Extract text from HTML (strip tags)
                $html = file_get_contents($filePath);
                return strip_tags($html);
            default:
                return null;
        }
    }

    /**
     * Search for similar documents to a query.
     */
    public function similaritySearch(string $query, int $lessonId, int $k = 5): array
    {
        // Generate embedding for query
        $queryEmbedding = $this->embeddingGenerator->embedText($query);

        // Use pgvector <-> operator for cosine distance
        $results = DB::select(
            "SELECT id, lesson_id, source_type, source_id, chunk_text,
                    1 - (embedding <=> ?) as similarity
             FROM lesson_embeddings
             WHERE lesson_id = ?
             ORDER BY embedding <=> ?
             LIMIT ?",
            [json_encode($queryEmbedding), $lessonId, json_encode($queryEmbedding), $k]
        );

        return $results;
    }

    /**
     * Generate a RAG response using OLLAMA chat.
     */
    public function generateResponse(string $query, int $lessonId, string $systemPrompt = null): string
    {
        // Retrieve context
        $similarDocs = $this->similaritySearch($query, $lessonId, 5);
        $context = '';
        foreach ($similarDocs as $doc) {
            $context .= $doc->chunk_text . "\n\n";
        }

        // Build prompt
        $system = $systemPrompt ?? "You are a helpful teaching assistant. Use the provided context to answer the user's question accurately. If the answer is not in the context, say you don't know.";
        $prompt = "Context:\n" . $context . "\n\nQuestion: " . $query . "\n\nAnswer:";

        // Call OLLAMA chat
        $ollamaConnector = new OllamaConnector(config('ollama.base_url'));
        
        $chat = new \LLPhant\Chat\OllamaChat($ollamaConnector);
        $chat->setModel(config('ollama.llm_model'));

        $response = $chat->generateText($prompt, $system);
        return $response;
    }
    
}