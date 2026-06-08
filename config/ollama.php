<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Ollama connection
    |--------------------------------------------------------------------------
    |
    | LocalMind talks to a locally-running Ollama instance. Everything runs
    | offline on the user's machine — no data leaves the laptop.
    |
    */

    'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),

    'default_model' => env('OLLAMA_DEFAULT_MODEL', 'llama3.2:1b'),

    // Seconds. Small local models on a slow disk can take a while to load.
    'timeout' => (int) env('OLLAMA_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Generation options passed to Ollama
    |--------------------------------------------------------------------------
    */

    'options' => [
        'temperature' => 0.7,
        'num_ctx'     => 4096,
    ],

    /*
    |--------------------------------------------------------------------------
    | Selectable models (shown in the per-conversation model switcher)
    |--------------------------------------------------------------------------
    */

    'models' => [
        'llama3.2:1b' => ['label' => 'Fast (1B)',  'hint' => 'Quick, lighter answers'],
        'qwen2.5:3b'  => ['label' => 'Smart (3B)', 'hint' => 'Better quality, slower'],
    ],

    /*
    |--------------------------------------------------------------------------
    | File-attachment limits (enforced on BOTH client and server)
    |--------------------------------------------------------------------------
    |
    | A 1B–3B model on 8GB RAM cannot ingest large files, so we extract text,
    | truncate hard, and feed only a budgeted slice to the model.
    |
    */

    'uploads' => [
        'max_files'           => 3,
        'max_file_bytes'      => 5 * 1024 * 1024,   // 5 MB
        'max_total_bytes'     => 10 * 1024 * 1024,  // 10 MB
        'allowed_ext'         => ['txt', 'md', 'csv', 'json', 'pdf', 'docx'],
        'context_char_budget' => 6000,
    ],

];
