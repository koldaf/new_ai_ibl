<?php
return [
    'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
    'embedding_model' => env('EMBEDDING_MODEL', 'nomic-embed-text'),
    'llm_model' => env('OLLAMA_CHAT_MODEL', 'llama3'),
];