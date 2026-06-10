<?php

namespace App\Contracts;

use Generator;

interface LlmProvider
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return Generator<int, string>
     */
    public function stream(array $messages, array $options = []): Generator;
}
