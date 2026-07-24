<?php
return [
    'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
    'embedding_model' => env('EMBEDDING_MODEL', 'embeddinggema'),
    'llm_model' => env('OLLAMA_CHAT_MODEL', 'gemma2:2b'),
    // Separate, optionally larger model for correctness-sensitive answer classification
    // (grading student responses). Defaults to the same model as llm_model until set —
    // small models are more prone to misjudging correctness and to echoing prompt/JSON
    // schema text back into their output ("template leakage").
    'classification_model' => env('OLLAMA_CLASSIFICATION_MODEL', env('OLLAMA_CHAT_MODEL', 'gemma2:2b')),
    'request_timeout' => (int) env('OLLAMA_REQUEST_TIMEOUT', 300),
    'embedding_timeout' => (int) env('OLLAMA_EMBEDDING_TIMEOUT', 120),
    'health_timeout' => (int) env('OLLAMA_HEALTH_TIMEOUT', 10),
    // Context window Ollama reserves per request. Larger values cost more RAM/compute
    // for the KV cache regardless of how much of it the actual prompt uses, so keep this
    // matched to real prompt sizes rather than a model's (often much larger) default.
    'num_ctx' => (int) env('OLLAMA_NUM_CTX', 4096),
];