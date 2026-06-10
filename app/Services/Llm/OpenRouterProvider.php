<?php

namespace App\Services\Llm;

use App\Contracts\LlmProvider;
use Generator;
use Illuminate\Support\Facades\Http;

class OpenRouterProvider implements LlmProvider
{
    public function __construct(private array $config) {}

    public function chat(array $messages, array $options = []): string
    {
        $response = $this->requestWithRateLimitRetry(function () use ($messages, $options) {
            return Http::withToken($this->config['api_key'])
                ->withHeaders([
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name'),
                ])
                ->timeout(60)
                ->retry(2, 1000, throw: false)
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $options['model'] ?? $this->config['model'],
                    'messages' => $messages,
                ]);
        });

        if ($response->failed()) {
            throw new \RuntimeException('OpenRouter API error: ' . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    public function stream(array $messages, array $options = []): Generator
    {
        $response = $this->requestWithRateLimitRetry(function () use ($messages, $options) {
            return Http::withToken($this->config['api_key'])
                ->withHeaders([
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name'),
                ])
                ->withOptions(['stream' => true])
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $options['model'] ?? $this->config['model'],
                    'messages' => $messages,
                    'stream' => true,
                ]);
        });

        if ($response->failed()) {
            throw new \RuntimeException('OpenRouter API error: ' . $response->body());
        }

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

    /**
     * 免費模型常遇到上游 429 限流，依 Retry-After 等待後重試。
     *
     * @param  callable():\Illuminate\Http\Client\Response  $request
     */
    private function requestWithRateLimitRetry(callable $request, int $maxAttempts = 3): \Illuminate\Http\Client\Response
    {
        $attempt = 0;

        do {
            $response = $request();
            $attempt++;

            if ($response->status() !== 429 || $attempt >= $maxAttempts) {
                return $response;
            }

            $wait = (int) ($response->header('Retry-After') ?: 5);
            $wait = max(1, min($wait, 15));
            sleep($wait);
        } while ($attempt < $maxAttempts);

        return $response;
    }
}
