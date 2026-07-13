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

    // Which provider drives AI calls. New drivers register in ProviderManager.
    'default_provider' => env('AI_PROVIDER', 'ollama'),

    'providers' => [
        'ollama' => [
            'driver' => 'ollama',
        ],
        // Future: 'openai', 'anthropic', 'gemini', 'deepseek', 'qwen'.
    ],

    /*
    | Per-task model resolution. Each task names a Settings key (admin-managed,
    | takes precedence) and a config default. ModelRouter::modelFor() reads this.
    */
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

    /*
    | Generation defaults. Same Settings-first pattern; ModelRouter merges these
    | with per-call overrides.
    */
    'generation' => [
        'temperature'    => ['setting' => 'ai_temperature',    'default' => (float) env('AI_TEMPERATURE', 0.7)],
        'top_p'          => ['setting' => 'ai_top_p',          'default' => (float) env('AI_TOP_P', 0.9)],
        'max_tokens'     => ['setting' => 'ai_max_tokens',     'default' => (int) env('AI_MAX_TOKENS', 1024)],
        'context_length' => ['setting' => 'ai_context_length', 'default' => (int) env('AI_CONTEXT_LENGTH', 4096)],
    ],

    'streaming' => ['setting' => 'ai_streaming', 'default' => (bool) env('AI_STREAMING', true)],

    // Seconds to cache deterministic AI results (used by higher-level services).
    'cache_ttl' => ['setting' => 'ai_cache_ttl', 'default' => (int) env('AI_CACHE_TTL', 300)],

];
