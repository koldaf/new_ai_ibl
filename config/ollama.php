<?php
return [
    'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
    'embedding_model' => env('EMBEDDING_MODEL', 'embeddinggema'),
    'llm_model' => env('OLLAMA_CHAT_MODEL', 'gemma2:2b'),
];