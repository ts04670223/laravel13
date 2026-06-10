<?php

namespace App\Services\Llm;

use App\Contracts\LlmProvider;
use Generator;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements LlmProvider
{
    public function __construct(private array $config) {}

    public function chat(array $messages, array $options = []): string
    {
        $systemMessage = '';
        $filteredMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message['content'];
            } else {
                $filteredMessages[] = $message;
            }
        }

        $payload = [
            'model' => $options['model'] ?? $this->config['model'],
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages' => $filteredMessages,
        ];

        if ($systemMessage) {
            $payload['system'] = $systemMessage;
        }

        $response = Http::withHeaders([
                'x-api-key' => $this->config['api_key'],
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(60)
            ->retry(2, 1000, throw: false)
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Anthropic API error: ' . $response->body());
        }

        return $response->json('content.0.text');
    }

    public function stream(array $messages, array $options = []): Generator
    {
        $systemMessage = '';
        $filteredMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message['content'];
            } else {
                $filteredMessages[] = $message;
            }
        }

        $payload = [
            'model' => $options['model'] ?? $this->config['model'],
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages' => $filteredMessages,
            'stream' => true,
        ];

        if ($systemMessage) {
            $payload['system'] = $systemMessage;
        }

        $response = Http::withHeaders([
                'x-api-key' => $this->config['api_key'],
                'anthropic-version' => '2023-06-01',
            ])
            ->withOptions(['stream' => true])
            ->post('https://api.anthropic.com/v1/messages', $payload);

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
                $json = json_decode($data, true);

                if (($json['type'] ?? '') === 'content_block_delta') {
                    $content = $json['delta']['text'] ?? '';
                    if ($content !== '') {
                        yield $content;
                    }
                }

                if (($json['type'] ?? '') === 'message_stop') {
                    return;
                }
            }
        }
    }
}
