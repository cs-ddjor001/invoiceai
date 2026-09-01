<?php

return [
    'base_url' => env('LLAMA_SERVER_URL', 'http://localhost:8080/v1'),
    'model' => env('LLAMA_MODEL', 'Negentropy-claude-opus-4.7-4B-Q4_K_M.gguf'),
    'timeout' => (int) env('EXTRACTION_TIMEOUT', 180),
    'temperature' => (float) env('EXTRACTION_TEMPERATURE', 0.1),
    'max_tokens' => (int) env('EXTRACTION_MAX_TOKENS', 8192),
    'enable_thinking' => (bool) env('EXTRACTION_ENABLE_THINKING', false),
];
