<?php

return [
    'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
    'model'    => env('OLLAMA_MODEL', 'llama3.2'),
    'timeout'  => (int) env('OLLAMA_TIMEOUT', 120),
];
