<?php

namespace App\Services\Llm;

use App\Contracts\LlmProvider;
use Generator;
use Illuminate\Support\Facades\Http;

class OllamaProvider implements LlmProvider
{
    public function __construct(private array $config) {}

    public function chat(array $messages, array $options = []): string
    {
        $response = Http::timeout(120)
            ->retry(2, 1000, throw: false)
            ->post($this->config['base_url'] . '/api/chat', [
                'model' => $options['model'] ?? $this->config['model'],
                'messages' => $messages,
                'stream' => false,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Ollama API error: ' . $response->body());
        }

        return $response->json('message.content');
    }

    public function stream(array $messages, array $options = []): Generator
    {
        $response = Http::timeout(120)
            ->withOptions(['stream' => true])
            ->post($this->config['base_url'] . '/api/chat', [
                'model' => $options['model'] ?? $this->config['model'],
                'messages' => $messages,
                'stream' => true,
            ]);

        $body = $response->toPsrResponse()->getBody();

        $buffer = '';
        while (!$body->eof()) {
            $buffer .= $body->read(1024);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if (empty(trim($line))) {
                    continue;
                }

                $json = json_decode($line, true);
                if ($json === null) {
                    continue;
                }

                if ($json['done'] ?? false) {
                    return;
                }

                $content = $json['message']['content'] ?? '';
                if ($content !== '') {
                    yield $content;
                }
            }
        }
    }
}
