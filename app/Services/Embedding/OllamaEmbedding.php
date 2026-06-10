<?php

namespace App\Services\Embedding;

use App\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;

class OllamaEmbedding implements EmbeddingProvider
{
    public function __construct(private array $config) {}

    public function embed(string $text): array
    {
        $response = Http::timeout(30)
            ->retry(2, 1000, throw: false)
            ->post($this->config['base_url'] . '/api/embed', [
                'model' => $this->config['embedding_model'],
                'input' => $text,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Ollama Embedding error: ' . $response->body());
        }

        return $response->json('embeddings.0');
    }

    public function embedBatch(array $texts): array
    {
        return array_map(fn ($text) => $this->embed($text), $texts);
    }
}
