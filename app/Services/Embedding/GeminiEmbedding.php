<?php

namespace App\Services\Embedding;

use App\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;

class GeminiEmbedding implements EmbeddingProvider
{
    public function __construct(private array $config) {}

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    public function embedBatch(array $texts): array
    {
        $model = $this->config['embedding_model'];
        $modelPath = str_starts_with($model, 'models/') ? $model : "models/{$model}";
        $dimensions = (int) config('ai.embedding_dimensions', 768);

        $requests = array_map(fn ($text) => [
            'model' => $modelPath,
            'content' => ['parts' => [['text' => $text]]],
            'outputDimensionality' => $dimensions,
        ], $texts);

        $response = Http::timeout(30)
            ->retry(2, 1000, throw: false)
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/{$modelPath}:batchEmbedContents?key=" . $this->config['api_key'],
                ['requests' => $requests]
            );

        if ($response->failed()) {
            throw new \RuntimeException('Gemini Embedding error: ' . $response->body());
        }

        return array_map(
            fn ($item) => $item['values'],
            $response->json('embeddings')
        );
    }
}
