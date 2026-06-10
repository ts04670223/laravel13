<?php

return [
    'default_llm' => env('AI_LLM_PROVIDER', 'openrouter'),
    'default_embedding' => env('AI_EMBEDDING_PROVIDER', 'openrouter'),
    'embedding_dimensions' => env('AI_EMBEDDING_DIMENSIONS', 1536),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'embedding_model' => env('ANTHROPIC_EMBEDDING_MODEL', 'voyage-3'),
        ],
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3'),
            'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
        ],
        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
            'embedding_model' => env('OPENROUTER_EMBEDDING_MODEL', 'openai/text-embedding-3-small'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
            'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'text-embedding-004'),
        ],
    ],

    'chunking' => [
        'chunk_size' => 1000,
        'chunk_overlap' => 200,
    ],

    'retrieval' => [
        'top_k' => 5,
        'score_threshold' => 0.7,
    ],
];
