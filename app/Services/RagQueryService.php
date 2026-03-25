<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonEmbedding;
use App\Services\AiPerformanceLogger;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LLPhant\Embeddings\VectorStores\FileSystem\FileSystemVectorStore;
use LLPhant\Embeddings\Document;

class RagQueryService
{
    private const STAGES = ['engage', 'explore', 'explain', 'elaborate', 'evaluate'];

    private OllamaEmbeddingGenerator $embeddingGenerator;
    private string $llmModel;
    private string $ollamaUrl;
    /** Tracks the chunk count set by retrieveContext() so callLlm() logContext can include it. */
    private int $lastChunkCount = 0;

    public function __construct()
    {
        $this->embeddingGenerator = new OllamaEmbeddingGenerator(
            model: config('ollama.embedding_model', 'embeddinggemma'),
            baseUrl: config('ollama.base_url', 'http://ollama:11434')
        );

        $this->llmModel = config('ollama.llm_model', 'qwen3:0.6b');
        $this->ollamaUrl = rtrim(config('ollama.base_url', 'http://ollama:11434'), '/');
    }

    /**
     * Generate a RAG response for a student's question about a lesson.
     * Reads the vector store from the lesson's vector_store_path.
     *
     * @param string $query The student's question
     * @param int $lessonId The lesson ID
     * @param string|null $systemPrompt Optional custom system prompt
     * @param int $topK Number of most relevant chunks to retrieve (default: 5)
     * @return string The AI-generated answer
     * @throws \RuntimeException If lesson not found or embeddings not ready
     */
    public function generateResponse(
        string $query,
        int $lessonId,
        ?string $stage = 'engage',
        int $topK = 5
    ): string {
        // 1. Load the lesson and validate it has embeddings
        $lesson = Lesson::findOrFail($lessonId);

        if ($lesson->processing_status !== 'completed') {
            throw new \RuntimeException(
                "Lesson embeddings are not ready yet. Status: {$lesson->processing_status}"
            );
        }

        if (empty($lesson->vector_store_path) || !File::exists($lesson->vector_store_path)) {
            throw new \RuntimeException(
                "Vector store file not found for lesson {$lessonId}"
            );
        }

        Log::info('[RAG Query] Processing question', [
            'lesson_id' => $lessonId,
            'question' => $query,
        ]);

        // 2. Verify Ollama service is reachable before proceeding
        if (!$this->isOllamaHealthy()) {
            throw new \RuntimeException(
                "Ollama service is not reachable at {$this->ollamaUrl}. " .
                "Please ensure Ollama is running and accessible."
            );
        }

        // 3. Retrieve relevant context from the vector store
        $context = $this->retrieveContext($lesson, $query, $topK);

        if (empty($context)) {
            return 'Not in context. Ask another question, please keep it short and specific.';
        }

        // 4. Generate response using stage-specific strict prompt
        $logContext = [
            'caller'           => 'rag_query',
            'lesson_id'        => $lessonId,
            'stage'            => $stage,
            'question_snippet' => $query,
            'context_chunks'   => $this->lastChunkCount,
        ];
        $answer = $this->generateLlmResponse($query, $context, $stage, $logContext);

        Log::info('[RAG Query] Response generated', [
            'lesson_id' => $lessonId,
            'answer_length' => strlen($answer),
        ]);

        return $answer;
    }

    /**
     * Retrieve relevant context from the vector store for a given query.
     *
     * @param Lesson $lesson The lesson object
     * @param string $query The user's question
     * @param int $topK Number of chunks to retrieve
     * @return string Concatenated relevant text chunks
     */
    private function retrieveContext(Lesson $lesson, string $query, int $topK): string
    {
        try {
            // Load the vector store from the lesson's stored path
            $vectorStore = new FileSystemVectorStore($lesson->vector_store_path);

            // Embed the query
            $queryDoc = new Document();
            $queryDoc->content = $query;
            $embeddedQuery = $this->embeddingGenerator->embedDocument($queryDoc);

            // Perform similarity search (pass embedding array, not Document)
            $similarDocuments = $vectorStore->similaritySearch($embeddedQuery->embedding, $topK);

            // Extract and concatenate the content
            $context = '';
            foreach ($similarDocuments as $doc) {
                $context .= trim($doc->content) . "\n\n";
            }

            $this->lastChunkCount = count($similarDocuments);

            Log::debug('[RAG Query] Context retrieved', [
                'lesson_id' => $lesson->id,
                'chunks_found' => $this->lastChunkCount,
                'context_length' => strlen($context),
            ]);

            return trim($context);

        } catch (\Throwable $e) {
            Log::error('[RAG Query] Context retrieval failed', [
                'lesson_id' => $lesson->id,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "Failed to retrieve context from vector store: {$e->getMessage()}"
            );
        }
    }

    /**
     * Safely retrieve relevant context for callers that need graceful fallback.
     */
    public function retrieveContextSafe(Lesson $lesson, string $query, int $topK = 5): string
    {
        try {
            return $this->retrieveContext($lesson, $query, $topK);
        } catch (\Throwable $e) {
            Log::warning('[RAG Query] Safe context retrieval returned empty result', [
                'lesson_id' => $lesson->id,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Generic Ollama LLM call for internal and cross-service use.
     *
     * @param array $logContext Optional performance-log metadata (caller, lesson_id, stage, etc.)
     */
    public function callLlm(string $prompt, string $system, int $maxTokens = 80, array $logContext = []): string
    {
        $callStart = microtime(true);
        try {
            $response = Http::timeout(180)
                ->post("{$this->ollamaUrl}/api/generate", [
                    'model' => $this->llmModel,
                    'prompt' => $prompt,
                    'system' => $system,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.1,
                        'num_predict' => $maxTokens,
                    ],
                ]);

            $wallClockMs = (microtime(true) - $callStart) * 1000;

            if ($response->failed()) {
                AiPerformanceLogger::logError(
                    $wallClockMs,
                    array_merge(['caller' => 'rag_query', 'model_name' => $this->llmModel], $logContext),
                    "Ollama API request failed: {$response->status()}"
                );
                throw new \RuntimeException(
                    "Ollama API request failed: {$response->body()}"
                );
            }

            $data = $response->json();

            if (!isset($data['response'])) {
                throw new \RuntimeException(
                    'Unexpected response format from Ollama'
                );
            }

            AiPerformanceLogger::log(
                $data,
                $wallClockMs,
                array_merge(['caller' => 'rag_query', 'model_name' => $this->llmModel], $logContext)
            );

            return trim($data['response']);

        } catch (\Throwable $e) {
            Log::error('[RAG Query] LLM generation failed', [
                'error' => $e->getMessage(),
                'ollama_url' => $this->ollamaUrl,
                'model' => $this->llmModel,
            ]);

            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')) {
                throw new \RuntimeException(
                    "Ollama service is not responding. This may be because:\n" .
                    "1. Ollama is still loading the model (first-time use can take 1-3 minutes)\n" .
                    "2. Ollama service is not running\n" .
                    "3. The model '{$this->llmModel}' is not available\n" .
                    'Please check that Ollama is running and try again.'
                );
            }

            throw new \RuntimeException(
                "Failed to generate response: {$e->getMessage()}"
            );
        }
    }

    /**
     * Generate a response using Ollama's LLM with the given context.
     *
     * @param string $query The user's question
     * @param string $context The retrieved relevant context
     * @param string|null $systemPrompt Optional system prompt override
     * @return string The generated answer
     */
    private function generateLlmResponse(
        string $query,
        string $context,
        ?string $stage = 'engage',
        array $logContext = []
    ): string {
        $system = $this->buildStageSystemPrompt($stage ?? 'engage');

        $userPrompt = "<CONTEXT>\n{$context}\n</CONTEXT>\n\nQuestion: {$query}";

        return $this->callLlm($userPrompt, $system, 80, $logContext);
    }

    /**
     * Build strict, stage-specific system prompts.
     */
    private function buildStageSystemPrompt(string $stage): string
    {
        $normalizedStage = in_array($stage, self::STAGES, true) ? $stage : 'engage';

        $stageDirective = match ($normalizedStage) {
            'engage' => 'Focus on curiosity, prior knowledge, and motivation.',
            'explore' => 'Focus on observations, patterns, and discovery from evidence.',
            'explain' => 'Focus on clear concept explanation and correct vocabulary.',
            'elaborate' => 'Focus on applying ideas to a new but related situation.',
            'evaluate' => 'Focus on concise judgment, correctness, and feedback.',
            default => 'Focus on accurate, concise instructional support.',
        };

        return "You are assisting the '{$normalizedStage}' stage of a lesson. {$stageDirective}\n\n" .
            "<CONTEXT>\n{{retrieved_chunks}}\n</CONTEXT>\n\n" .
            "You must answer ONLY using the information inside <CONTEXT>.\n" .
            "If the answer is not present, say: \"Oh, I am not sure, please ask another question.\"\n" .
            "Keep the answer under 30 words.";
    }

    /**
     * Check if a lesson has embeddings ready for RAG queries.
     *
     * @param int $lessonId
     * @return bool
     */
    public function isReady(int $lessonId): bool
    {
        $lesson = Lesson::find($lessonId);

        if (!$lesson) {
            return false;
        }

        return $lesson->processing_status === 'completed' 
            && !empty($lesson->vector_store_path)
            && File::exists($lesson->vector_store_path);
    }

    /**
     * Get the processing status of a lesson's embeddings.
     *
     * @param int $lessonId
     * @return array Status information
     */
    public function getStatus(int $lessonId): array
    {
        $lesson = Lesson::find($lessonId);

        if (!$lesson) {
            return [
                'ready' => false,
                'status' => 'not_found',
                'message' => 'Lesson not found',
            ];
        }

        $embeddingCount = LessonEmbedding::where('lesson_id', $lessonId)->count();

        return [
            'ready' => $this->isReady($lessonId),
            'status' => $lesson->processing_status,
            'vector_store_path' => $lesson->vector_store_path,
            'embedding_count' => $embeddingCount,
            'message' => $this->getStatusMessage($lesson->processing_status),
        ];
    }

    /**
     * Get a human-readable status message.
     *
     * @param string $status
     * @return string
     */
    private function getStatusMessage(string $status): string
    {
        return match ($status) {
            'completed' => 'Embeddings are ready. You can ask questions about this lesson.',
            'processing' => 'Embeddings are being generated. Please wait...',
            'pending' => 'Embeddings have not been generated yet.',
            'failed' => 'Embedding generation failed. Please try again or contact support.',
            default => 'Unknown status',
        };
    }

    /**
     * Check if Ollama service is reachable and responding.
     *
     * @return bool
     */
    public function isOllamaHealthy(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->ollamaUrl}/api/tags");
            Log::debug('[RAG] Ollama health check response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'success' => $response->successful(),
            ]);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('[RAG] Ollama health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get information about available models in Ollama.
     *
     * @return array
     */
    public function getOllamaModels(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->ollamaUrl}/api/tags");
            
            if ($response->successful()) {
                return $response->json()['models'] ?? [];
            }
            
            return [];
        } catch (\Throwable $e) {
            Log::error('[RAG] Failed to fetch Ollama models', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
