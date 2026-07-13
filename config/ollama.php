<?php
return [
    'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
    'embedding_model' => env('EMBEDDING_MODEL', 'embeddinggema'),
    'llm_model' => env('OLLAMA_CHAT_MODEL', 'gemma2:2b'),
    'request_timeout' => (int) env('OLLAMA_REQUEST_TIMEOUT', 300),
    'embedding_timeout' => (int) env('OLLAMA_EMBEDDING_TIMEOUT', 120),
    'health_timeout' => (int) env('OLLAMA_HEALTH_TIMEOUT', 10),
];