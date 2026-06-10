<?php

namespace App\Services;

use App\Contracts\EmbeddingProvider;
use App\Models\DocumentChunk;
use Pgvector\Laravel\Distance;
use Pgvector\Laravel\Vector;

class Retriever
{
    public function __construct(
        private EmbeddingProvider $embeddingProvider,
        private int $topK = 5,
        private float $scoreThreshold = 0.7,
    ) {}

    /**
     * @return array<int, array{chunk: DocumentChunk, score: float}>
     */
    public function retrieve(string $query, ?int $userId = null): array
    {
        $queryEmbedding = $this->embeddingProvider->embed($query);
        $vector = new Vector($queryEmbedding);

        $queryBuilder = DocumentChunk::query()
            ->nearestNeighbors('embedding', $vector, Distance::Cosine)
            ->limit($this->topK);

        if ($userId !== null) {
            $queryBuilder->whereHas('document', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        $chunks = $queryBuilder->get();

        return $chunks
            ->map(function ($chunk) {
                return [
                    'chunk' => $chunk,
                    'score' => 1 - $chunk->neighbor_distance,
                ];
            })
            ->filter(fn ($item) => $item['score'] >= $this->scoreThreshold)
            ->values()
            ->toArray();
    }
}
