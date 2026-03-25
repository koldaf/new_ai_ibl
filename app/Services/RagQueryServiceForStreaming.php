<?php

namespace App\Services;

use App\Models\Course;
use App\Models\RagConversation;
use App\Services\AiPerformanceLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LLPhant\Embeddings\VectorStores\FileSystem\FileSystemVectorStore;
use LLPhant\Embeddings\Document;

class RagQueryServiceForStreaming
{
    private OllamaEmbeddingGenerator $embeddingGenerator;
    private string $llmModel;
    private string $ollamaUrl;

    public function __construct()
    {
        $this->embeddingGenerator = new OllamaEmbeddingGenerator(
            model: config('services.ollama.embedding_model', 'nomic-embed-text'),
            baseUrl: config('services.ollama.url', 'http://ollama:11434')
        );
        
        $this->llmModel = config('services.ollama.llm_model', 'phi3');
        $this->ollamaUrl = config('services.ollama.url', 'http://ollama:11434');
    }

    /**
     * Process a query and return answer with sources (non-streaming)
     */
    public function query(Course $course, string $question, int $userId): array
    {
        $startTime = microtime(true);
        
        Log::info("RAG Query received", [
            'course_id' => $course->id,
            'question' => $question,
            'user_id' => $userId
        ]);

        // Step 1: Generate embedding for the question
        $questionEmbedding = $this->embeddingGenerator->embedText($question);

        // Step 2: Find relevant documents
        $relevantDocuments = $this->findRelevantDocuments($course, $questionEmbedding);

        if (empty($relevantDocuments)) {
            return [
                'answer' => "I couldn't find any relevant information in the course materials to answer your question. Please make sure course materials have been uploaded and processed.",
                'sources' => [],
                'context_used' => false
            ];
        }

        // Step 3: Build context
        $context = $this->buildContext($relevantDocuments);

        // Step 4: Generate answer
        $answer = $this->generateAnswer($question, $context, [
            'user_id'          => $userId,
            'question_snippet' => $question,
            'context_chunks'   => count($relevantDocuments),
        ]);

        // Step 5: Extract sources
        $sources = $this->extractSources($relevantDocuments);

        // Calculate response time
        $responseTime = (int)((microtime(true) - $startTime) * 1000);

        // Save conversation history
        RagConversation::create([
            'course_id' => $course->id,
            'user_id' => $userId,
            'question' => $question,
            'answer' => $answer,
            'sources' => $sources,
            'context_used' => true,
            'response_time_ms' => $responseTime,
        ]);

        Log::info("RAG Query completed", [
            'course_id' => $course->id,
            'sources_found' => count($sources),
            'response_time_ms' => $responseTime
        ]);

        return [
            'answer' => $answer,
            'sources' => $sources,
            'context_used' => true
        ];
    }

    /**
     * Process a query with streaming response
     */
    public function queryStream(Course $course, string $question, int $userId): \Generator
    {
        $startTime = microtime(true);
        
        Log::info("RAG Streaming Query received", [
            'course_id' => $course->id,
            'question' => $question,
            'user_id' => $userId
        ]);

        // Step 1: Generate embedding
        $questionEmbedding = $this->embeddingGenerator->embedText($question);

        // Step 2: Find relevant documents
        $relevantDocuments = $this->findRelevantDocuments($course, $questionEmbedding);

        if (empty($relevantDocuments)) {
            yield json_encode([
                'type' => 'answer',
                'content' => "I couldn't find any relevant information in the course materials.",
                'done' => true
            ]) . "\n";
            return;
        }

        // Step 3: Build context
        $context = $this->buildContext($relevantDocuments);

        // Step 4: Extract sources and send first
        $sources = $this->extractSources($relevantDocuments);
        yield json_encode([
            'type' => 'sources',
            'content' => $sources
        ]) . "\n";

        // Step 5: Stream the answer
        $fullAnswer = '';
        $prompt = $this->buildPrompt($question, $context);

        $client = Http::timeout(120)->withOptions(['stream' => true]);

        $requestSentAt   = microtime(true);
        $firstTokenMs    = null;   // wall-clock TTFT
        $lastDoneData    = [];     // final Ollama chunk contains perf metadata
        
        $response = $client->post("{$this->ollamaUrl}/api/generate", [
            'model' => $this->llmModel,
            'prompt' => $prompt,
            'stream' => true,
        ]);

        $stream = $response->toPsrResponse()->getBody();

        while (!$stream->eof()) {
            $line = $this->readLine($stream);
            
            if (empty($line)) {
                continue;
            }

            try {
                $data = json_decode($line, true);
                
                if (isset($data['response'])) {
                    $token = $data['response'];
                    // Capture first-token arrival time (TTFT)
                    if ($firstTokenMs === null && $token !== '') {
                        $firstTokenMs = (microtime(true) - $requestSentAt) * 1000;
                    }
                    $fullAnswer .= $token;
                    
                    yield json_encode([
                        'type' => 'answer',
                        'content' => $token,
                        'done' => $data['done'] ?? false
                    ]) . "\n";
                }

                if (isset($data['done']) && $data['done']) {
                    $lastDoneData = $data; // contains eval_count, eval_duration, etc.
                    break;
                }
            } catch (\Exception $e) {
                Log::error("Error parsing stream", ['error' => $e->getMessage()]);
            }
        }

        // Calculate response time
        $responseTime = (int)((microtime(true) - $startTime) * 1000);

        // Log to AI performance table
        AiPerformanceLogger::log(
            $lastDoneData,
            (float) $responseTime,
            [
                'caller'           => 'stream_query',
                'model_name'       => $this->llmModel,
                'user_id'          => $userId,
                'question_snippet' => $question,
                'context_chunks'   => count($relevantDocuments),
                'ttft_ms'          => $firstTokenMs,  // wall-clock TTFT overrides Ollama prompt_eval_duration
            ]
        );

        // Save conversation history
        RagConversation::create([
            'course_id' => $course->id,
            'user_id' => $userId,
            'question' => $question,
            'answer' => $fullAnswer,
            'sources' => $sources,
            'context_used' => true,
            'response_time_ms' => $responseTime,
        ]);

        Log::info("RAG Streaming Query completed", [
            'course_id' => $course->id,
            'response_time_ms' => $responseTime
        ]);
    }

    /**
     * Read a line from stream
     */
    private function readLine($stream): string
    {
        $line = '';
        while (!$stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $line .= $char;
        }
        return trim($line);
    }

    /**
     * Find relevant documents using vector similarity
     */
    private function findRelevantDocuments(Course $course, array $questionEmbedding, int $topK = 5): array
    {
        $allDocuments = [];

        $knowledgeBases = $course->knowledgeBases()
            ->where('processing_status', 'completed')
            ->whereNotNull('vector_store_path')
            ->get();

        foreach ($knowledgeBases as $kb) {
            if (!file_exists($kb->vector_store_path)) {
                Log::warning("Vector store file not found", [
                    'path' => $kb->vector_store_path
                ]);
                continue;
            }

            try {
                $vectorStore = new FileSystemVectorStore($kb->vector_store_path);
                $documents = $vectorStore->similaritySearch($questionEmbedding, $topK);
                $allDocuments = array_merge($allDocuments, $documents);
            } catch (\Exception $e) {
                Log::error("Error searching vector store", [
                    'kb_id' => $kb->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        usort($allDocuments, function($a, $b) {
            return ($b->embedding ?? 0) <=> ($a->embedding ?? 0);
        });

        return array_slice($allDocuments, 0, $topK);
    }

    /**
     * Build context string from documents
     */
    private function buildContext(array $documents): string
    {
        $contextParts = [];
        
        foreach ($documents as $index => $doc) {
            $contextParts[] = "Source " . ($index + 1) . ":\n" . $doc->content;
        }
        
        return implode("\n\n---\n\n", $contextParts);
    }

    /**
     * Generate answer using Ollama LLM (non-streaming)
     */
    private function generateAnswer(string $question, string $context, array $logContext = []): string
    {
        $prompt = $this->buildPrompt($question, $context);
        $callStart = microtime(true);

        try {
            $response = Http::timeout(120)->post("{$this->ollamaUrl}/api/generate", [
                'model' => $this->llmModel,
                'prompt' => $prompt,
                'stream' => false,
            ]);

            $wallClockMs = (microtime(true) - $callStart) * 1000;

            if ($response->failed()) {
                AiPerformanceLogger::logError(
                    $wallClockMs,
                    array_merge(['caller' => 'stream_query', 'model_name' => $this->llmModel], $logContext),
                    "Ollama API request failed: {$response->status()}"
                );
                throw new \Exception("Ollama API request failed: " . $response->body());
            }

            $data = $response->json();

            AiPerformanceLogger::log(
                is_array($data) ? $data : [],
                $wallClockMs,
                array_merge(['caller' => 'stream_query', 'model_name' => $this->llmModel], $logContext)
            );

            return $data['response'] ?? 'Sorry, I could not generate an answer.';
            
        } catch (\Exception $e) {
            Log::error("Error generating answer", [
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception("Failed to generate answer: " . $e->getMessage());
        }
    }

    /**
     * Build the prompt for the LLM
     */
    private function buildPrompt(string $question, string $context): string
    {
        return <<<PROMPT
You are a helpful educational assistant. Answer the student's question based ONLY on the provided context from course materials.

Context:
{$context}

Student's Question: {$question}

Instructions:
- Provide a clear, accurate answer based on the context provided
- If the context doesn't contain enough information to answer the question, say so
- Be concise but thorough
- Use a friendly, educational tone
- Do not make up information not present in the context

Answer:
PROMPT;
    }

    /**
     * Extract source information from documents
     */
    private function extractSources(array $documents): array
    {
        $sources = [];
        
        foreach ($documents as $doc) {
            if (isset($doc->metadata)) {
                $sources[] = [
                    'knowledge_base_id' => $doc->metadata['knowledge_base_id'] ?? null,
                    'title' => $doc->metadata['title'] ?? 'Unknown',
                    'chunk_index' => $doc->metadata['chunk_index'] ?? 0,
                ];
            }
        }
        
        return $sources;
    }

    /**
     * Get conversation history for a course and user
     */
    public function getConversationHistory(Course $course, int $userId, int $limit = 50): array
    {
        return RagConversation::where('course_id', $course->id)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($conversation) {
                return [
                    'id' => $conversation->id,
                    'question' => $conversation->question,
                    'answer' => $conversation->answer,
                    'sources' => $conversation->sources,
                    'created_at' => $conversation->created_at->diffForHumans(),
                    'response_time' => $conversation->response_time_ms,
                ];
            })
            ->toArray();
    }
}