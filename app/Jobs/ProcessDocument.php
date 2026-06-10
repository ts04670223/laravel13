<?php

namespace App\Jobs;

use App\Contracts\EmbeddingProvider;
use App\Models\Document;
use App\Services\DocumentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(private Document $document) {}

    public function handle(DocumentProcessor $processor, EmbeddingProvider $embeddingProvider): void
    {
        $this->document->update(['status' => 'processing']);

        try {
            // 1. Extract text based on source type
            $text = match ($this->document->source_type) {
                'file' => $processor->extractText(
                    Storage::disk('private')->path($this->document->source_path),
                    $this->document->mime_type
                ),
                'url' => $this->extractFromUrl($this->document->source_path),
                'database' => $this->document->metadata['content'] ?? '',
            };

            if (empty(trim($text))) {
                throw new \RuntimeException('No text content extracted from document.');
            }

            // 2. Chunk the text
            $chunks = $processor->chunkText($text);

            // 3. Generate embeddings in batches of 20
            $batchSize = 20;
            $chunkBatches = array_chunk($chunks, $batchSize);

            $chunkIndex = 0;
            foreach ($chunkBatches as $batch) {
                $embeddings = $embeddingProvider->embedBatch($batch);

                foreach ($batch as $i => $chunkContent) {
                    $this->document->chunks()->create([
                        'content' => $chunkContent,
                        'embedding' => $embeddings[$i],
                        'chunk_index' => $chunkIndex++,
                    ]);
                }
            }

            // 4. Update document status
            $this->document->update([
                'status' => 'completed',
                'metadata' => array_merge($this->document->metadata ?? [], [
                    'chunk_count' => count($chunks),
                    'char_count' => strlen($text),
                ]),
            ]);
        } catch (\Throwable $e) {
            $this->document->update([
                'status' => 'failed',
                'metadata' => array_merge($this->document->metadata ?? [], [
                    'error' => $e->getMessage(),
                ]),
            ]);

            throw $e;
        }
    }

    private function extractFromUrl(string $url): string
    {
        $response = Http::timeout(30)->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch URL: {$url}");
        }

        return strip_tags($response->body());
    }
}
