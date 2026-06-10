<?php

namespace Tests\Unit;

use App\Contracts\EmbeddingProvider;
use App\Models\DocumentChunk;
use App\Services\Retriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetrieverTest extends TestCase
{
    use RefreshDatabase;

    public function test_retrieve_returns_empty_when_no_chunks(): void
    {
        $embedding = \Mockery::mock(EmbeddingProvider::class);
        $embedding->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.0));

        $retriever = new Retriever($embedding);
        $results = $retriever->retrieve('test query', 5);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
}
