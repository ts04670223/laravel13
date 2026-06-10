<?php

namespace App\Services\Llm;

use App\Contracts\LlmProvider;
use Generator;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements LlmProvider
{
    public function __construct(private array $config) {}

    public function chat(array $messages, array $options = []): string
    {
        $response = Http::withToken($this->config['api_key'])
            ->timeout(60)
            ->retry(2, 1000, throw: false)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $options['model'] ?? $this->config['model'],
                'messages' => $messages,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    public function stream(array $messages, array $options = []): Generator
    {
        $response = Http::withToken($this->config['api_key'])
            ->withOptions(['stream' => true])
            ->post('https://api.openai.com/v1/chat/completions', [
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

                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6);
                if ($data === '[DONE]') {
                    return;
                }

                $json = json_decode($data, true);
                $content = $json['choices'][0]['delta']['content'] ?? '';
                if ($content !== '') {
                    yield $content;
                }
            }
        }
    }
}
