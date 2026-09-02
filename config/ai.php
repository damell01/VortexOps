<?php

/*
|--------------------------------------------------------------------------
| AI platform configuration
|--------------------------------------------------------------------------
|
| The AI layer (app/AI) reads everything from here. Model names and generation
| defaults live as config with a Settings-key override: the admin panel wins at
| runtime, config is the fallback. Nothing about a model or provider is hardcoded
| inside the service classes — change behaviour here or in Settings, not in code.
|
*/

return [

    'default_provider' => env('AI_PROVIDER', 'ollama'),

    'providers' => [
        'ollama' => [
            'driver' => 'ollama',
        ],
        'openai' => [
            'driver'   => 'openai',
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key'  => env('OPENAI_API_KEY'),
        ],
    ],

    'tasks' => [
        'chat' => [
            'setting' => 'ollama_chat_model',
            'default' => env('AI_CHAT_MODEL', 'llama3.2:3b'),
        ],
        'fast' => [
            'setting' => 'ollama_fast_model',
            'default' => env('AI_FAST_MODEL', 'llama3.2:1b'),
        ],
        'reasoning' => [
            'setting' => 'ollama_reasoning_model',
            'default' => env('AI_REASONING_MODEL', 'llama3.2:3b'),
        ],
        'vision' => [
            'setting' => 'ollama_vision_model',
            'default' => env('AI_VISION_MODEL', 'moondream'),
        ],
        'embedding' => [
            'setting' => 'ollama_embedding_model',
            'default' => env('AI_EMBEDDING_MODEL', 'nomic-embed-text'),
        ],
        'json' => [
            'setting' => 'ollama_json_model',
            'default' => env('AI_JSON_MODEL', 'llama3.2:3b'),
        ],
    ],

    'generation' => [
        'temperature'    => ['setting' => 'ai_temperature',    'default' => (float) env('AI_TEMPERATURE', 0.7)],
        'top_p'          => ['setting' => 'ai_top_p',          'default' => (float) env('AI_TOP_P', 0.9)],
        'max_tokens'     => ['setting' => 'ai_max_tokens',     'default' => (int) env('AI_MAX_TOKENS', 1024)],
        'context_length' => ['setting' => 'ai_context_length', 'default' => (int) env('AI_CONTEXT_LENGTH', 4096)],
    ],

    'streaming' => ['setting' => 'ai_streaming', 'default' => (bool) env('AI_STREAMING', true)],

    'cache_ttl' => ['setting' => 'ai_cache_ttl', 'default' => (int) env('AI_CACHE_TTL', 300)],

    /*
    | Low-resource operations mode
    |--------------------------------------------------------------------------
    | Background-only is intentionally true by default. When enabled, normal
    | Filament/Livewire matching paths stop before embeddings/LLM calls and only
    | dedicated `ai` queue jobs contact the model server.
    */
    'ops' => [
        'enabled'           => (bool) env('AI_OPS_ENABLED', true),
        'use_llm'           => (bool) env('AI_OPS_USE_LLM', true),
        'background_only'   => (bool) env('AI_BACKGROUND_ONLY', true),
        'queue'             => env('AI_OPS_QUEUE', 'ai'),
        'max_tokens'        => (int) env('AI_OPS_MAX_TOKENS', 600),
        'context_length'    => (int) env('AI_OPS_CONTEXT_LENGTH', 2048),
        'max_payload_chars' => (int) env('AI_OPS_MAX_PAYLOAD_CHARS', 12000),
    ],

    'memory' => [
        'ttl'   => (int) env('AI_MEMORY_TTL', 3600),
        'turns' => (int) env('AI_MEMORY_TURNS', 20),
    ],

    'monitoring' => [
        'enabled' => (bool) env('AI_MONITORING', true),
    ],

];
