<?php

namespace App\Services;

use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;
use LLPhant\Embeddings\Document;
use Illuminate\Support\Facades\Http;

class OllamaEmbeddingGenerator implements EmbeddingGeneratorInterface
{
    private string $baseUrl;
    private string $model;
    /**
     * Create a new class instance.
     */
    public function __construct(string $model = 'embeddinggemma', string $baseUrl = 'http://ollama:11434')
    {
        //
        $this->model = $model;
        $this->baseUrl = $baseUrl;

    }
    /**
     * Generate embeddings for multiple documents
     */
    public function embedDocuments(array $documents): array
    {
        foreach ($documents as $document) {
            $embedding = $this->embedText($document->content);
            $document->embedding = $embedding;
        }

        return $documents;
    }

     /**
     * Generate embedding for a single text
     */
    public function embedText(string $text): array
    {
        $response = Http::timeout(60)
            ->post("{$this->baseUrl}/api/embeddings", [
                'model' => $this->model,
                'prompt' => $text,
            ]);

        if ($response->failed()) {
            throw new \Exception("Ollama embedding failed: " . $response->body());
        }

        $data = $response->json();

        if (!isset($data['embedding'])) {
            throw new \Exception("No embedding returned from Ollama");
        }

        return $data['embedding'];

    }

    /**
     * Generate embedding for a single Document and return it (implements interface)
     */
    public function embedDocument(Document $document): Document
    {
        $document->embedding = $this->embedText($document->content);
        return $document;
    }

    /**
     * Get the embedding length/dimension
     */
    public function getEmbeddingLength(): int
    {
        // nomic-embed-text produces 768-dimensional embeddings
        // Adjust based on your model
        return 768;
    }
}
