<?php

namespace Tests\Unit;

use App\Services\DocumentProcessor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentProcessorTest extends TestCase
{
    private DocumentProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new DocumentProcessor();
    }

    public function test_extract_text_from_plain_text(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test') . '.txt';
        file_put_contents($tmpFile, 'Hello World. This is a test.');

        $result = $this->processor->extractText($tmpFile, 'text/plain');

        $this->assertEquals('Hello World. This is a test.', $result);
        unlink($tmpFile);
    }

    public function test_chunk_text_splits_correctly(): void
    {
        $text = str_repeat('This is a sentence. ', 100); // ~2000 chars

        $chunks = $this->processor->chunkText($text);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(1200, strlen($chunk)); // chunk_size(1000) + tolerance
        }
    }

    public function test_chunk_text_single_chunk_for_short_text(): void
    {
        $text = 'Short text here.';

        $chunks = $this->processor->chunkText($text);

        $this->assertCount(1, $chunks);
        $this->assertEquals('Short text here.', $chunks[0]);
    }

    public function test_extract_from_url(): void
    {
        Http::fake([
            'https://example.com' => Http::response('<html><body><p>Page content here</p></body></html>', 200),
        ]);

        $result = $this->processor->extractFromUrl('https://example.com');

        $this->assertStringContainsString('Page content', $result);
    }
}
