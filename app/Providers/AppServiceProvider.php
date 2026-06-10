<?php

namespace App\Providers;

use App\Contracts\EmbeddingProvider;
use App\Contracts\LlmProvider;
use App\Services\DocumentProcessor;
use App\Services\Embedding\AnthropicEmbedding;
use App\Services\Embedding\GeminiEmbedding;
use App\Services\Embedding\OllamaEmbedding;
use App\Services\Embedding\OpenAiEmbedding;
use App\Services\Embedding\OpenRouterEmbedding;
use App\Services\Llm\AnthropicProvider;
use App\Services\Llm\OllamaProvider;
use App\Services\Llm\OpenAiProvider;
use App\Services\Llm\OpenRouterProvider;
use App\Services\Retriever;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmProvider::class, fn () => match (config('ai.default_llm')) {
            'openai' => new OpenAiProvider(config('ai.providers.openai')),
            'anthropic' => new AnthropicProvider(config('ai.providers.anthropic')),
            'ollama' => new OllamaProvider(config('ai.providers.ollama')),
            'openrouter' => new OpenRouterProvider(config('ai.providers.openrouter')),
            default => new OpenRouterProvider(config('ai.providers.openrouter')),
        });

        $this->app->bind(EmbeddingProvider::class, fn () => match (config('ai.default_embedding')) {
            'openai' => new OpenAiEmbedding(config('ai.providers.openai')),
            'anthropic' => new AnthropicEmbedding(config('ai.providers.anthropic')),
            'ollama' => new OllamaEmbedding(config('ai.providers.ollama')),
            'openrouter' => new OpenRouterEmbedding(config('ai.providers.openrouter')),
            'gemini' => new GeminiEmbedding(config('ai.providers.gemini')),
            default => new OpenRouterEmbedding(config('ai.providers.openrouter')),
        });

        $this->app->bind(DocumentProcessor::class, fn () => new DocumentProcessor(
            chunkSize: config('ai.chunking.chunk_size'),
            chunkOverlap: config('ai.chunking.chunk_overlap'),
        ));

        $this->app->bind(Retriever::class, fn ($app) => new Retriever(
            embeddingProvider: $app->make(EmbeddingProvider::class),
            topK: config('ai.retrieval.top_k'),
            scoreThreshold: config('ai.retrieval.score_threshold'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
