<?php

namespace App\Services;

use App\Contracts\LlmProvider;
use App\Models\Conversation;
use App\Models\Message;
use Generator;

class RagPipeline
{
    public function __construct(
        private LlmProvider $llmProvider,
        private Retriever $retriever,
    ) {}

    public function answer(string $question, Conversation $conversation): Message
    {
        $userId = $conversation->user_id;

        // 1. Retrieve relevant chunks
        $results = $this->retriever->retrieve($question, $userId);

        // 2. Build context from retrieved chunks
        $context = $this->buildContext($results);

        // 3. Build messages array with conversation history
        $messages = $this->buildMessages($conversation, $question, $context);

        // 4. Get LLM response
        $answer = $this->llmProvider->chat($messages);

        // 5. Save user message
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $question,
        ]);

        // 6. Save assistant message with sources
        $sources = array_map(fn ($r) => [
            'chunk_id' => $r['chunk']->id,
            'document_title' => $r['chunk']->document->title,
            'content_preview' => mb_substr($r['chunk']->content, 0, 200),
            'score' => round($r['score'], 4),
        ], $results);

        $assistantMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $answer,
            'sources' => $sources,
        ]);

        return $assistantMessage;
    }

    public function stream(string $question, Conversation $conversation): Generator
    {
        $userId = $conversation->user_id;
        $results = $this->retriever->retrieve($question, $userId);
        $context = $this->buildContext($results);
        $messages = $this->buildMessages($conversation, $question, $context);

        // Save user message
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $question,
        ]);

        // Stream LLM response
        $fullContent = '';
        foreach ($this->llmProvider->stream($messages) as $chunk) {
            $fullContent .= $chunk;
            yield ['type' => 'chunk', 'content' => $chunk];
        }

        // Yield sources
        $sources = array_map(fn ($r) => [
            'chunk_id' => $r['chunk']->id,
            'document_title' => $r['chunk']->document->title,
            'content_preview' => mb_substr($r['chunk']->content, 0, 200),
            'score' => round($r['score'], 4),
        ], $results);

        yield ['type' => 'sources', 'sources' => $sources];

        // Save assistant message
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $fullContent,
            'sources' => $sources,
        ]);

        yield ['type' => 'done'];
    }

    private function buildContext(array $results): string
    {
        if (empty($results)) {
            return '找不到相關資料。請根據你的一般知識回答，並告知使用者這個回答不是基於已上傳的文件。';
        }

        $context = "以下是從使用者文件中檢索到的相關片段：\n\n";
        foreach ($results as $i => $result) {
            $context .= "--- 片段 " . ($i + 1) . " (來源: {$result['chunk']->document->title}) ---\n";
            $context .= $result['chunk']->content . "\n\n";
        }

        return $context;
    }

    private function buildMessages(Conversation $conversation, string $question, string $context): array
    {
        $systemPrompt = <<<PROMPT
你是一個知識助手。根據提供的文件內容回答使用者的問題。
規則：
1. 優先使用提供的文件內容來回答
2. 如果文件內容不足以回答，請明確告知
3. 引用資料時說明來源
4. 使用繁體中文回答
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Add recent conversation history (last 10 messages)
        $history = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse();

        foreach ($history as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        // Add current question with context
        $messages[] = [
            'role' => 'user',
            'content' => "文件內容：\n{$context}\n\n問題：{$question}",
        ];

        return $messages;
    }
}
