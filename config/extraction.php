<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Extraction driver
    |--------------------------------------------------------------------------
    |
    | Which InvoiceExtractor implementation the container resolves. `llm` reads
    | the PDF's text layer and asks the model to structure it. `vision` is
    | declared but not implemented — it needs PDF-to-image support that Windows
    | makes awkward. `fake` returns canned data for tests and for deployments
    | where llama-server cannot follow.
    |
    */

    'driver' => env('EXTRACTION_DRIVER', 'llm'),

    /*
    |--------------------------------------------------------------------------
    | llama-server
    |--------------------------------------------------------------------------
    |
    | The model id is read from config rather than guessed. The source app
    | fuzzy-matched the string "qwen" against /v1/models and fell back to
    | whatever was first in the list, which made it impossible to know from the
    | code which model produced a given result.
    |
    */

    'base_url' => env('LLAMA_SERVER_URL', 'http://localhost:8080/v1'),

    'model' => env('LLAMA_MODEL', 'Negentropy-claude-opus-4.7-4B-Q4_K_M.gguf'),

    // Local inference on CPU is slow; the source app saw 5–15s per invoice.
    'timeout' => (int) env('EXTRACTION_TIMEOUT', 120),

    // Low but non-zero: extraction should be near-deterministic.
    'temperature' => (float) env('EXTRACTION_TEMPERATURE', 0.1),

    'max_tokens' => (int) env('EXTRACTION_MAX_TOKENS', 8192),

    /*
    |--------------------------------------------------------------------------
    | Prompt payload ceiling
    |--------------------------------------------------------------------------
    |
    | Page text is truncated to this many characters before being sent, so a
    | long PDF cannot push the instructions out of the context window.
    |
    */

    'max_payload_chars' => (int) env('EXTRACTION_MAX_PAYLOAD_CHARS', 20000),

];
