<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentProcessor
{
    public function __construct(
        private int $chunkSize = 1000,
        private int $chunkOverlap = 200,
    ) {}

    public function extractText(string $filePath, string $mimeType): string
    {
        return match (true) {
            str_contains($mimeType, 'pdf') => $this->extractPdf($filePath),
            str_contains($mimeType, 'wordprocessingml') || str_ends_with($filePath, '.docx') => $this->extractDocx($filePath),
            default => file_get_contents($filePath),
        };
    }

    public function extractFromUrl(string $url): string
    {
        $html = Http::get($url)->body();
        return strip_tags($html);
    }

    public function chunkText(string $text): array
    {
        $text = trim($text);

        if (strlen($text) <= $this->chunkSize) {
            return [$text];
        }

        $chunks = [];
        $start = 0;
        $textLength = strlen($text);

        while ($start < $textLength) {
            $end = min($start + $this->chunkSize, $textLength);

            // Try to break at sentence boundary
            if ($end < $textLength) {
                $lastPeriod = strrpos(substr($text, $start, $end - $start), '. ');
                $lastNewline = strrpos(substr($text, $start, $end - $start), "\n");
                $breakPoint = max($lastPeriod ?: 0, $lastNewline ?: 0);

                if ($breakPoint > $this->chunkOverlap) {
                    $end = $start + $breakPoint + 1;
                }
            }

            $chunks[] = trim(substr($text, $start, $end - $start));
            $start = max($start + 1, $end - $this->chunkOverlap);
        }

        return array_filter($chunks, fn ($chunk) => $chunk !== '');
    }

    private function extractPdf(string $filePath): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);
        return $pdf->getText();
    }

    private function extractDocx(string $filePath): string
    {
        $phpWord = WordIOFactory::load($filePath);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }

        return $text;
    }
}
