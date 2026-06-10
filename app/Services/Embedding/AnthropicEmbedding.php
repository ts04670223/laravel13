<?php

namespace App\Services\Embedding;

use App\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;

class AnthropicEmbedding implements EmbeddingProvider
{
    public function __construct(private array $config) {}

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    public function embedBatch(array $texts): array
    {
        $response = Http::withToken($this->config['api_key'])
            ->timeout(30)
            ->retry(2, 1000, throw: false)
            ->post('https://api.voyageai.com/v1/embeddings', [
                'model' => $this->config['embedding_model'],
                'input' => $texts,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Voyage AI Embedding error: ' . $response->body());
        }

        return array_map(
            fn ($item) => $item['embedding'],
            $response->json('data')
        );
    }
}
