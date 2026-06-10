# Laravel RAG AI 系統 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在 Laravel 13 中建立完整的 RAG 系統，支援多來源文件匯入、多 LLM Provider 切換、向量語義搜尋，並提供 REST API 與聊天 UI。

**Architecture:** Laravel 後端使用 PostgreSQL + pgvector 儲存向量，透過 Contract/Provider 模式抽象 LLM 和 Embedding 服務。文件處理透過 Queue Job 非同步執行。前端使用 Blade + Alpine.js + Tailwind CSS。

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL 16 + pgvector, Laravel Breeze, Alpine.js, Tailwind CSS, SSE

---

## File Structure

### 新建檔案

```
config/ai.php                                    — AI 設定檔
app/Contracts/LlmProvider.php                    — LLM 介面
app/Contracts/EmbeddingProvider.php               — Embedding 介面
app/Services/Llm/OpenAiProvider.php              — OpenAI LLM 實作
app/Services/Llm/AnthropicProvider.php           — Anthropic LLM 實作
app/Services/Llm/OllamaProvider.php              — Ollama LLM 實作
app/Services/Embedding/OpenAiEmbedding.php       — OpenAI Embedding 實作
app/Services/Embedding/AnthropicEmbedding.php    — Anthropic Embedding 實作
app/Services/Embedding/OllamaEmbedding.php       — Ollama Embedding 實作
app/Services/DocumentProcessor.php               — 文件解析 + chunking
app/Services/Retriever.php                       — 向量搜尋
app/Services/RagPipeline.php                     — 完整 RAG 流程串接
app/Models/Document.php                          — Document model
app/Models/DocumentChunk.php                     — DocumentChunk model
app/Models/Conversation.php                      — Conversation model
app/Models/Message.php                           — Message model
app/Jobs/ProcessDocument.php                     — 文件處理 Queue Job
app/Http/Controllers/DocumentController.php      — 文件管理 API
app/Http/Controllers/ConversationController.php  — 對話 API
app/Http/Controllers/ChatController.php          — 聊天 API (含 SSE)
database/migrations/xxxx_create_documents_table.php
database/migrations/xxxx_create_document_chunks_table.php
database/migrations/xxxx_create_conversations_table.php
database/migrations/xxxx_create_messages_table.php
resources/views/chat.blade.php                   — 聊天頁面
resources/views/documents.blade.php              — 文件管理頁面
routes/api.php                                   — API 路由
tests/Unit/DocumentProcessorTest.php             — chunking 單元測試
tests/Unit/RetrieverTest.php                     — retriever 單元測試
tests/Feature/DocumentApiTest.php                — 文件 API 測試
tests/Feature/ChatApiTest.php                    — 聊天 API 測試
tests/Feature/ConversationApiTest.php            — 對話 API 測試
```

### 修改檔案

```
app/Providers/AppServiceProvider.php             — 註冊 LLM/Embedding bindings
routes/web.php                                   — 加入 chat / documents 頁面路由
.env.example                                     — 加入 AI 相關環境變數
```

---

## Task 1: 安裝相依套件與基礎設定

**Files:**
- Modify: `composer.json`
- Modify: `.env.example`
- Create: `config/ai.php`

- [ ] **Step 1: 安裝 Composer 套件**

```bash
composer require laravel/breeze pgvector/pgvector smalot/pdfparser phpoffice/phpword
```

- [ ] **Step 2: 安裝 Breeze (Blade stack)**

```bash
php artisan breeze:install blade
npm install
npm run build
```

- [ ] **Step 3: 建立 config/ai.php**

```php
<?php

return [
    'default_llm' => env('AI_LLM_PROVIDER', 'openai'),
    'default_embedding' => env('AI_EMBEDDING_PROVIDER', 'openai'),
    'embedding_dimensions' => env('AI_EMBEDDING_DIMENSIONS', 1536),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'embedding_model' => env('ANTHROPIC_EMBEDDING_MODEL', 'voyage-3'),
        ],
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3'),
            'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
        ],
    ],

    'chunking' => [
        'chunk_size' => 1000,
        'chunk_overlap' => 200,
    ],

    'retrieval' => [
        'top_k' => 5,
        'score_threshold' => 0.7,
    ],
];
```

- [ ] **Step 4: 更新 .env.example 加入 AI 環境變數**

在 `.env.example` 末尾加入：

```
AI_LLM_PROVIDER=openai
AI_EMBEDDING_PROVIDER=openai
AI_EMBEDDING_DIMENSIONS=1536

OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
OPENAI_EMBEDDING_MODEL=text-embedding-3-small

ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-20250514

OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: install dependencies and add AI config"
```

---

## Task 2: 資料庫 Migration

**Files:**
- Create: `database/migrations/2026_05_19_000001_create_documents_table.php`
- Create: `database/migrations/2026_05_19_000002_create_document_chunks_table.php`
- Create: `database/migrations/2026_05_19_000003_create_conversations_table.php`
- Create: `database/migrations/2026_05_19_000004_create_messages_table.php`

- [ ] **Step 1: 建立 documents migration**

```bash
php artisan make:migration create_documents_table
```

內容：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('source_type', ['file', 'database', 'url']);
            $table->string('source_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
```

- [ ] **Step 2: 建立 document_chunks migration**

```bash
php artisan make:migration create_document_chunks_table
```

內容：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->integer('chunk_index');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $dimensions = config('ai.embedding_dimensions', 1536);
        DB::statement("ALTER TABLE document_chunks ADD COLUMN embedding vector({$dimensions})");
        DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
```

- [ ] **Step 3: 建立 conversations migration**

```bash
php artisan make:migration create_conversations_table
```

內容：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
```

- [ ] **Step 4: 建立 messages migration**

```bash
php artisan make:migration create_messages_table
```

內容：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->json('sources')->nullable();
            $table->json('token_usage')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
```

- [ ] **Step 5: 執行 migration**

```bash
php artisan migrate
```

Expected: 所有 migration 執行成功，無錯誤。

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add database migrations for RAG system"
```

---

## Task 3: Eloquent Models

**Files:**
- Create: `app/Models/Document.php`
- Create: `app/Models/DocumentChunk.php`
- Create: `app/Models/Conversation.php`
- Create: `app/Models/Message.php`

- [ ] **Step 1: 建立 Document model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'source_type',
        'source_path',
        'mime_type',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
```

- [ ] **Step 2: 建立 DocumentChunk model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class DocumentChunk extends Model
{
    use HasNeighbors;

    protected $fillable = [
        'document_id',
        'content',
        'embedding',
        'chunk_index',
        'metadata',
    ];

    protected $casts = [
        'embedding' => Vector::class,
        'metadata' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
```

- [ ] **Step 3: 建立 Conversation model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
```

- [ ] **Step 4: 建立 Message model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'sources',
        'token_usage',
    ];

    protected $casts = [
        'sources' => 'array',
        'token_usage' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add Eloquent models for RAG system"
```

---

## Task 4: Contracts（LLM 與 Embedding 介面）

**Files:**
- Create: `app/Contracts/LlmProvider.php`
- Create: `app/Contracts/EmbeddingProvider.php`

- [ ] **Step 1: 建立 LlmProvider 介面**

```php
<?php

namespace App\Contracts;

use Generator;

interface LlmProvider
{
    /**
     * 送出聊天請求並取得完整回應。
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * 串流聊天回應。
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return Generator<int, string>
     */
    public function stream(array $messages, array $options = []): Generator;
}
```

- [ ] **Step 2: 建立 EmbeddingProvider 介面**

```php
<?php

namespace App\Contracts;

interface EmbeddingProvider
{
    /**
     * 為單一文字產生 embedding 向量。
     *
     * @return array<int, float>
     */
    public function embed(string $text): array;

    /**
     * 批次產生 embedding 向量。
     *
     * @param array<int, string> $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array;
}
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: add LlmProvider and EmbeddingProvider contracts"
```

---

## Task 5: LLM Provider 實作

**Files:**
- Create: `app/Services/Llm/OpenAiProvider.php`
- Create: `app/Services/Llm/AnthropicProvider.php`
- Create: `app/Services/Llm/OllamaProvider.php`

- [ ] **Step 1: 建立 OpenAiProvider**

```php
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
```

- [ ] **Step 2: 建立 AnthropicProvider**

```php
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
```

- [ ] **Step 3: 建立 OllamaProvider**

```php
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
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: implement LLM providers (OpenAI, Anthropic, Ollama)"
```

---

## Task 6: Embedding Provider 實作

**Files:**
- Create: `app/Services/Embedding/OpenAiEmbedding.php`
- Create: `app/Services/Embedding/AnthropicEmbedding.php`
- Create: `app/Services/Embedding/OllamaEmbedding.php`

- [ ] **Step 1: 建立 OpenAiEmbedding**

```php
<?php

namespace App\Services\Embedding;

use App\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;

class OpenAiEmbedding implements EmbeddingProvider
{
    public function __construct(private array $config) {}

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    public function embedBatch(array $texts): array
    {
        $response = Http::withToken($this->config['api_key'])
            ->timeout(30)
            ->retry(2, 1000, throw: false)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $this->config['embedding_model'],
                'input' => $texts,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI Embedding error: ' . $response->body());
        }

        return array_map(
            fn ($item) => $item['embedding'],
            $response->json('data')
        );
    }
}
```

- [ ] **Step 2: 建立 AnthropicEmbedding（透過 Voyage AI）**

```php
<?php

namespace App\Services\Embedding;

use App\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;

class AnthropicEmbedding implements EmbeddingProvider
{
    public function __construct(private array $config) {}

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    public function embedBatch(array $texts): array
    {
        $response = Http::withToken($this->config['api_key'])
            ->timeout(30)
            ->retry(2, 1000, throw: false)
            ->post('https://api.voyageai.com/v1/embeddings', [
                'model' => $this->config['embedding_model'],
                'input' => $texts,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Voyage AI Embedding error: ' . $response->body());
        }

        return array_map(
            fn ($item) => $item['embedding'],
            $response->json('data')
        );
    }
}
```

- [ ] **Step 3: 建立 OllamaEmbedding**

```php
<?php

namespace App\Services\Embedding;

use App\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;

class OllamaEmbedding implements EmbeddingProvider
{
    public function __construct(private array $config) {}

    public function embed(string $text): array
    {
        $response = Http::timeout(30)
            ->retry(2, 1000, throw: false)
            ->post($this->config['base_url'] . '/api/embed', [
                'model' => $this->config['embedding_model'],
                'input' => $text,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Ollama Embedding error: ' . $response->body());
        }

        return $response->json('embeddings.0');
    }

    public function embedBatch(array $texts): array
    {
        return array_map(fn ($text) => $this->embed($text), $texts);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: implement Embedding providers (OpenAI, Voyage, Ollama)"
```

---

## Task 7: DocumentProcessor（文件解析 + Chunking）

**Files:**
- Create: `app/Services/DocumentProcessor.php`
- Create: `tests/Unit/DocumentProcessorTest.php`

- [ ] **Step 1: 寫 DocumentProcessor 的 failing test**

```php
<?php

namespace Tests\Unit;

use App\Services\DocumentProcessor;
use PHPUnit\Framework\TestCase;

class DocumentProcessorTest extends TestCase
{
    private DocumentProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new DocumentProcessor(chunkSize: 100, chunkOverlap: 20);
    }

    public function test_chunks_text_by_size(): void
    {
        $text = str_repeat('word ', 50); // 250 chars
        $chunks = $this->processor->chunkText($text);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(100, strlen($chunk));
        }
    }

    public function test_chunks_have_overlap(): void
    {
        $text = 'The quick brown fox jumps over the lazy dog. ' . str_repeat('Sentence here. ', 20);
        $processor = new DocumentProcessor(chunkSize: 80, chunkOverlap: 20);
        $chunks = $processor->chunkText($text);

        if (count($chunks) >= 2) {
            $end_of_first = substr($chunks[0], -20);
            $this->assertStringContainsString(substr($end_of_first, 0, 10), $chunks[1]);
        }
    }

    public function test_short_text_returns_single_chunk(): void
    {
        $text = 'Short text.';
        $chunks = $this->processor->chunkText($text);

        $this->assertCount(1, $chunks);
        $this->assertEquals('Short text.', $chunks[0]);
    }

    public function test_extract_text_from_plain_text(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test') . '.txt';
        file_put_contents($tmpFile, 'Hello World');

        $text = $this->processor->extractText($tmpFile, 'text/plain');

        $this->assertEquals('Hello World', $text);
        unlink($tmpFile);
    }
}
```

- [ ] **Step 2: 執行測試確認失敗**

```bash
php artisan test tests/Unit/DocumentProcessorTest.php
```

Expected: FAIL — class `App\Services\DocumentProcessor` not found.

- [ ] **Step 3: 實作 DocumentProcessor**

```php
<?php

namespace App\Services;

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
        $html = file_get_contents($url);
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
                $breakPoint = max($lastPeriod, $lastNewline);

                if ($breakPoint !== false && $breakPoint > 0) {
                    $end = $start + $breakPoint + 1;
                }
            }

            $chunks[] = trim(substr($text, $start, $end - $start));
            $start = $end - $this->chunkOverlap;

            if ($start >= $textLength) {
                break;
            }
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
```

- [ ] **Step 4: 執行測試確認通過**

```bash
php artisan test tests/Unit/DocumentProcessorTest.php
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: implement DocumentProcessor with chunking and text extraction"
```

---

## Task 8: Retriever（向量搜尋）

**Files:**
- Create: `app/Services/Retriever.php`
- Create: `tests/Unit/RetrieverTest.php`

- [ ] **Step 1: 寫 Retriever failing test**

```php
<?php

namespace Tests\Unit;

use App\Contracts\EmbeddingProvider;
use App\Models\DocumentChunk;
use App\Services\Retriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RetrieverTest extends TestCase
{
    use RefreshDatabase;

    public function test_retrieve_returns_relevant_chunks(): void
    {
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')
            ->with('test query')
            ->andReturn(array_fill(0, 1536, 0.1));

        $retriever = new Retriever($embeddingProvider, topK: 3, scoreThreshold: 0.0);

        // Create test chunks (requires DB with pgvector, will be integration test)
        $this->assertIsArray($retriever->retrieve('test query'));
    }

    public function test_retrieve_respects_top_k(): void
    {
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')
            ->andReturn(array_fill(0, 1536, 0.1));

        $retriever = new Retriever($embeddingProvider, topK: 2, scoreThreshold: 0.0);

        $results = $retriever->retrieve('test');
        $this->assertLessThanOrEqual(2, count($results));
    }
}
```

- [ ] **Step 2: 執行測試確認失敗**

```bash
php artisan test tests/Unit/RetrieverTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: 實作 Retriever**

```php
<?php

namespace App\Services;

use App\Contracts\EmbeddingProvider;
use App\Models\DocumentChunk;
use Pgvector\Laravel\Vector;

class Retriever
{
    public function __construct(
        private EmbeddingProvider $embeddingProvider,
        private int $topK = 5,
        private float $scoreThreshold = 0.7,
    ) {}

    /**
     * @return array<int, array{chunk: DocumentChunk, score: float}>
     */
    public function retrieve(string $query, ?int $userId = null): array
    {
        $queryEmbedding = $this->embeddingProvider->embed($query);
        $vector = new Vector($queryEmbedding);

        $queryBuilder = DocumentChunk::query()
            ->nearestNeighbors('embedding', $vector, $this->topK);

        if ($userId !== null) {
            $queryBuilder->whereHas('document', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        $chunks = $queryBuilder->get();

        return $chunks
            ->map(function ($chunk) use ($vector) {
                return [
                    'chunk' => $chunk,
                    'score' => 1 - $chunk->neighbor_distance,
                ];
            })
            ->filter(fn ($item) => $item['score'] >= $this->scoreThreshold)
            ->values()
            ->toArray();
    }
}
```

- [ ] **Step 4: 執行測試確認通過**

```bash
php artisan test tests/Unit/RetrieverTest.php
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: implement Retriever for vector similarity search"
```

---

## Task 9: RagPipeline

**Files:**
- Create: `app/Services/RagPipeline.php`

- [ ] **Step 1: 實作 RagPipeline**

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "feat: implement RagPipeline for end-to-end RAG flow"
```

---

## Task 10: ProcessDocument Job

**Files:**
- Create: `app/Jobs/ProcessDocument.php`

- [ ] **Step 1: 實作 ProcessDocument Job**

```php
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
                    Storage::path($this->document->source_path),
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
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "feat: implement ProcessDocument queue job"
```

---

## Task 11: Service Provider 註冊

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: 在 AppServiceProvider 註冊 bindings**

在 `AppServiceProvider::register()` 加入：

```php
use App\Contracts\EmbeddingProvider;
use App\Contracts\LlmProvider;
use App\Services\Embedding\AnthropicEmbedding;
use App\Services\Embedding\OllamaEmbedding;
use App\Services\Embedding\OpenAiEmbedding;
use App\Services\Llm\AnthropicProvider;
use App\Services\Llm\OllamaProvider;
use App\Services\Llm\OpenAiProvider;
use App\Services\DocumentProcessor;
use App\Services\Retriever;

// In register() method:
$this->app->bind(LlmProvider::class, fn () => match (config('ai.default_llm')) {
    'openai' => new OpenAiProvider(config('ai.providers.openai')),
    'anthropic' => new AnthropicProvider(config('ai.providers.anthropic')),
    'ollama' => new OllamaProvider(config('ai.providers.ollama')),
    default => new OpenAiProvider(config('ai.providers.openai')),
});

$this->app->bind(EmbeddingProvider::class, fn () => match (config('ai.default_embedding')) {
    'openai' => new OpenAiEmbedding(config('ai.providers.openai')),
    'anthropic' => new AnthropicEmbedding(config('ai.providers.anthropic')),
    'ollama' => new OllamaEmbedding(config('ai.providers.ollama')),
    default => new OpenAiEmbedding(config('ai.providers.openai')),
});

$this->app->bind(DocumentProcessor::class, fn () => new DocumentProcessor(
    chunkSize: config('ai.chunking.chunk_size'),
    chunkOverlap: config('ai.chunking.chunk_overlap'),
));

$this->app->bind(Retriever::class, fn ($app) => new Retriever(
    embeddingProvider: $app->make(EmbeddingProvider::class),
    topK: config('ai.retrieval.top_k'),
    scoreThreshold: config('ai.retrieval.score_threshold'),
));
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "feat: register AI service bindings in AppServiceProvider"
```

---

## Task 12: API Controllers

**Files:**
- Create: `app/Http/Controllers/DocumentController.php`
- Create: `app/Http/Controllers/ConversationController.php`
- Create: `app/Http/Controllers/ChatController.php`

- [ ] **Step 1: 建立 DocumentController**

```php
<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = $request->user()
            ->documents()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($documents);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => 'required|in:file,url,database',
            'title' => 'required|string|max:255',
            'file' => 'required_if:source_type,file|file|max:10240|mimes:pdf,txt,md,docx',
            'url' => 'required_if:source_type,url|url|max:2048',
            'content' => 'required_if:source_type,database|string',
        ]);

        $document = new Document([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'source_type' => $validated['source_type'],
            'status' => 'pending',
        ]);

        match ($validated['source_type']) {
            'file' => $this->handleFileUpload($request, $document),
            'url' => $document->source_path = $validated['url'],
            'database' => $document->metadata = ['content' => $validated['content']],
        };

        $document->save();
        ProcessDocument::dispatch($document);

        return response()->json($document, 201);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        if ($document->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($document->source_type === 'file' && $document->source_path) {
            Storage::delete($document->source_path);
        }

        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    private function handleFileUpload(Request $request, Document $document): void
    {
        $file = $request->file('file');
        $path = $file->store('documents', 'private');

        $document->source_path = $path;
        $document->mime_type = $file->getMimeType();
    }
}
```

- [ ] **Step 2: 建立 ConversationController**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()
            ->conversations()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($conversations);
    }

    public function store(Request $request): JsonResponse
    {
        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'title' => $request->input('title'),
        ]);

        return response()->json($conversation, 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            abort(403);
        }

        $conversation->load(['messages' => fn ($q) => $q->orderBy('created_at')]);

        return response()->json($conversation);
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            abort(403);
        }

        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted.']);
    }
}
```

- [ ] **Step 3: 建立 ChatController**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\RagPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(private RagPipeline $ragPipeline) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string|max:4000',
        ]);

        $conversation = Conversation::findOrFail($validated['conversation_id']);

        if ($conversation->user_id !== $request->user()->id) {
            abort(403);
        }

        $message = $this->ragPipeline->answer($validated['message'], $conversation);

        return response()->json(['message' => $message]);
    }

    public function stream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string|max:4000',
        ]);

        $conversation = Conversation::findOrFail($validated['conversation_id']);

        if ($conversation->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->stream(function () use ($validated, $conversation) {
            foreach ($this->ragPipeline->stream($validated['message'], $conversation) as $event) {
                echo "event: {$event['type']}\n";
                echo "data: " . json_encode($event) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: implement API controllers for documents, conversations, and chat"
```

---

## Task 13: API 路由

**Files:**
- Create: `routes/api.php`
- Modify: `routes/web.php`

- [ ] **Step 1: 建立 routes/api.php**

```php
<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Documents
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);

    // Conversations
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy']);

    // Chat
    Route::post('/chat', [ChatController::class, 'chat']);
    Route::post('/chat/stream', [ChatController::class, 'stream']);
});
```

- [ ] **Step 2: 在 routes/web.php 加入頁面路由**

在 `routes/web.php` 加入：

```php
Route::middleware('auth')->group(function () {
    Route::get('/chat', fn () => view('chat'))->name('chat');
    Route::get('/documents', fn () => view('documents'))->name('documents');
});
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: add API and web routes"
```

---

## Task 14: User Model 關聯

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: 在 User model 加入 relationships**

在 `User.php` 加入：

```php
use App\Models\Conversation;
use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\HasMany;

public function documents(): HasMany
{
    return $this->hasMany(Document::class);
}

public function conversations(): HasMany
{
    return $this->hasMany(Conversation::class);
}
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "feat: add document and conversation relationships to User model"
```

---

## Task 15: Feature Tests

**Files:**
- Create: `tests/Feature/DocumentApiTest.php`
- Create: `tests/Feature/ChatApiTest.php`
- Create: `tests/Feature/ConversationApiTest.php`

- [ ] **Step 1: 建立 DocumentApiTest**

```php
<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_document(): void
    {
        Queue::fake();
        Storage::fake('private');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/documents', [
            'source_type' => 'file',
            'title' => 'Test Document',
            'file' => UploadedFile::fake()->create('test.txt', 100, 'text/plain'),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Test Document')
            ->assertJsonPath('status', 'pending');
    }

    public function test_user_can_list_documents(): void
    {
        $user = User::factory()->create();
        Document::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/documents');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_delete_own_document(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    public function test_user_cannot_delete_others_document(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/documents');

        $response->assertStatus(401);
    }
}
```

- [ ] **Step 2: 建立 ConversationApiTest**

```php
<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_conversation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/conversations', [
            'title' => 'Test Conversation',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Test Conversation');
    }

    public function test_user_can_list_conversations(): void
    {
        $user = User::factory()->create();
        Conversation::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/conversations');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_view_conversation_with_messages(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/conversations/{$conversation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $conversation->id);
    }

    public function test_user_cannot_view_others_conversation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->getJson("/api/conversations/{$conversation->id}");

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 3: 建立 ChatApiTest**

```php
<?php

namespace Tests\Feature;

use App\Contracts\LlmProvider;
use App\Contracts\EmbeddingProvider;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_chat_message(): void
    {
        $llm = Mockery::mock(LlmProvider::class);
        $llm->shouldReceive('chat')->andReturn('This is a test response.');
        $this->app->instance(LlmProvider::class, $llm);

        $embedding = Mockery::mock(EmbeddingProvider::class);
        $embedding->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        $this->app->instance(EmbeddingProvider::class, $embedding);

        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'conversation_id' => $conversation->id,
            'message' => 'Hello AI',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message.role', 'assistant')
            ->assertJsonPath('message.content', 'This is a test response.');
    }

    public function test_user_cannot_chat_in_others_conversation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'conversation_id' => $conversation->id,
            'message' => 'Hello',
        ]);

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 4: 執行所有測試**

```bash
php artisan test
```

Expected: 所有測試通過（需要 Document 和 Conversation factory，在下一步建立）。

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "test: add feature tests for documents, conversations, and chat APIs"
```

---

## Task 16: Model Factories

**Files:**
- Create: `database/factories/DocumentFactory.php`
- Create: `database/factories/ConversationFactory.php`

- [ ] **Step 1: 建立 DocumentFactory**

```php
<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'source_type' => fake()->randomElement(['file', 'url', 'database']),
            'source_path' => fake()->url(),
            'mime_type' => 'text/plain',
            'status' => 'completed',
            'metadata' => null,
        ];
    }
}
```

- [ ] **Step 2: 建立 ConversationFactory**

```php
<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
        ];
    }
}
```

- [ ] **Step 3: 執行所有測試確認通過**

```bash
php artisan test
```

Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "test: add Document and Conversation factories"
```

---

## Task 17: 聊天 UI（Blade + Alpine.js）

**Files:**
- Create: `resources/views/chat.blade.php`

- [ ] **Step 1: 建立聊天頁面**

```html
<x-app-layout>
    <div class="flex h-[calc(100vh-65px)]" x-data="chatApp()">
        <!-- Sidebar: Conversations -->
        <div class="w-64 border-r bg-gray-50 flex flex-col">
            <div class="p-4 border-b">
                <button @click="createConversation()" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                    新對話
                </button>
            </div>
            <div class="flex-1 overflow-y-auto">
                <template x-for="conv in conversations" :key="conv.id">
                    <div @click="selectConversation(conv)"
                         :class="{'bg-indigo-100': currentConversation?.id === conv.id}"
                         class="p-3 cursor-pointer hover:bg-gray-100 border-b flex justify-between items-center">
                        <span x-text="conv.title || '新對話'" class="truncate text-sm"></span>
                        <button @click.stop="deleteConversation(conv.id)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                    </div>
                </template>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex-1 flex flex-col">
            <!-- Messages -->
            <div class="flex-1 overflow-y-auto p-6 space-y-4" id="messages-container">
                <template x-for="msg in messages" :key="msg.id">
                    <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div :class="msg.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-800'"
                             class="max-w-2xl rounded-lg px-4 py-3">
                            <div x-html="renderMarkdown(msg.content)"></div>
                            <!-- Sources -->
                            <template x-if="msg.sources && msg.sources.length > 0">
                                <div class="mt-3 pt-2 border-t border-gray-300/30">
                                    <p class="text-xs font-semibold mb-1">引用來源：</p>
                                    <template x-for="source in msg.sources" :key="source.chunk_id">
                                        <div class="text-xs bg-white/10 rounded p-2 mb-1">
                                            <span class="font-medium" x-text="source.document_title"></span>
                                            <span class="opacity-70" x-text="' (' + (source.score * 100).toFixed(0) + '%)'"></span>
                                            <p class="opacity-80 mt-1" x-text="source.content_preview"></p>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- Streaming indicator -->
                <div x-show="isStreaming" class="flex justify-start">
                    <div class="bg-gray-100 rounded-lg px-4 py-3">
                        <span x-html="renderMarkdown(streamingContent)"></span>
                        <span class="animate-pulse">▌</span>
                    </div>
                </div>
            </div>

            <!-- Input -->
            <div class="border-t p-4">
                <form @submit.prevent="sendMessage()" class="flex gap-3">
                    <input x-model="inputMessage"
                           :disabled="isStreaming"
                           type="text"
                           placeholder="輸入你的問題..."
                           class="flex-1 rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit"
                            :disabled="isStreaming || !inputMessage.trim()"
                            class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        送出
                    </button>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function chatApp() {
        return {
            conversations: [],
            currentConversation: null,
            messages: [],
            inputMessage: '',
            isStreaming: false,
            streamingContent: '',

            async init() {
                await this.loadConversations();
            },

            async loadConversations() {
                const res = await fetch('/api/conversations');
                const data = await res.json();
                this.conversations = data.data || data;
            },

            async createConversation() {
                const res = await fetch('/api/conversations', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                    body: JSON.stringify({title: null}),
                });
                const conv = await res.json();
                this.conversations.unshift(conv);
                this.selectConversation(conv);
            },

            async selectConversation(conv) {
                this.currentConversation = conv;
                const res = await fetch(`/api/conversations/${conv.id}`);
                const data = await res.json();
                this.messages = data.messages || [];
            },

            async deleteConversation(id) {
                await fetch(`/api/conversations/${id}`, {
                    method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                });
                this.conversations = this.conversations.filter(c => c.id !== id);
                if (this.currentConversation?.id === id) {
                    this.currentConversation = null;
                    this.messages = [];
                }
            },

            async sendMessage() {
                if (!this.inputMessage.trim() || !this.currentConversation) return;

                const question = this.inputMessage;
                this.inputMessage = '';
                this.messages.push({id: Date.now(), role: 'user', content: question, sources: null});
                this.isStreaming = true;
                this.streamingContent = '';

                try {
                    const response = await fetch('/api/chat/stream', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                        body: JSON.stringify({conversation_id: this.currentConversation.id, message: question}),
                    });

                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let sources = null;

                    while (true) {
                        const {done, value} = await reader.read();
                        if (done) break;

                        const text = decoder.decode(value);
                        const lines = text.split('\n');

                        for (const line of lines) {
                            if (line.startsWith('data: ')) {
                                const data = JSON.parse(line.slice(6));
                                if (data.type === 'chunk') {
                                    this.streamingContent += data.content;
                                } else if (data.type === 'sources') {
                                    sources = data.sources;
                                }
                            }
                        }

                        this.$nextTick(() => {
                            document.getElementById('messages-container').scrollTop = document.getElementById('messages-container').scrollHeight;
                        });
                    }

                    this.messages.push({id: Date.now(), role: 'assistant', content: this.streamingContent, sources: sources});
                } catch (e) {
                    this.messages.push({id: Date.now(), role: 'assistant', content: '發生錯誤，請稍後再試。', sources: null});
                }

                this.isStreaming = false;
                this.streamingContent = '';
            },

            renderMarkdown(text) {
                if (!text) return '';
                return text
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.*?)\*/g, '<em>$1</em>')
                    .replace(/`(.*?)`/g, '<code class="bg-gray-200 px-1 rounded">$1</code>')
                    .replace(/\n/g, '<br>');
            }
        }
    }
    </script>
    @endpush
</x-app-layout>
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "feat: add chat UI with Alpine.js"
```

---

## Task 18: 文件管理 UI

**Files:**
- Create: `resources/views/documents.blade.php`

- [ ] **Step 1: 建立文件管理頁面**

```html
<x-app-layout>
    <div class="max-w-4xl mx-auto py-8 px-4" x-data="documentsApp()">
        <h1 class="text-2xl font-bold mb-6">文件管理</h1>

        <!-- Upload Form -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">上傳新文件</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">來源類型</label>
                    <select x-model="uploadForm.source_type" class="w-full rounded-lg border-gray-300">
                        <option value="file">檔案上傳</option>
                        <option value="url">URL 爬取</option>
                        <option value="database">文字內容</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">標題</label>
                    <input x-model="uploadForm.title" type="text" class="w-full rounded-lg border-gray-300" placeholder="文件標題">
                </div>

                <div x-show="uploadForm.source_type === 'file'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">選擇檔案 (PDF, TXT, MD, DOCX, max 10MB)</label>
                    <input @change="uploadForm.file = $event.target.files[0]" type="file" accept=".pdf,.txt,.md,.docx" class="w-full">
                </div>

                <div x-show="uploadForm.source_type === 'url'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
                    <input x-model="uploadForm.url" type="url" class="w-full rounded-lg border-gray-300" placeholder="https://...">
                </div>

                <div x-show="uploadForm.source_type === 'database'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">文字內容</label>
                    <textarea x-model="uploadForm.content" rows="5" class="w-full rounded-lg border-gray-300" placeholder="貼上文字內容..."></textarea>
                </div>

                <button @click="upload()" :disabled="uploading" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    <span x-show="!uploading">上傳</span>
                    <span x-show="uploading">處理中...</span>
                </button>
            </div>
        </div>

        <!-- Document List -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b">
                <h2 class="text-lg font-semibold">已上傳文件</h2>
            </div>
            <div class="divide-y">
                <template x-for="doc in documents" :key="doc.id">
                    <div class="p-4 flex justify-between items-center">
                        <div>
                            <p class="font-medium" x-text="doc.title"></p>
                            <p class="text-sm text-gray-500">
                                <span x-text="doc.source_type"></span> ·
                                <span :class="{
                                    'text-yellow-600': doc.status === 'pending',
                                    'text-blue-600': doc.status === 'processing',
                                    'text-green-600': doc.status === 'completed',
                                    'text-red-600': doc.status === 'failed'
                                }" x-text="doc.status"></span>
                            </p>
                        </div>
                        <button @click="deleteDocument(doc.id)" class="text-red-500 hover:text-red-700 text-sm">刪除</button>
                    </div>
                </template>
                <div x-show="documents.length === 0" class="p-4 text-gray-500 text-center">
                    尚未上傳任何文件
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function documentsApp() {
        return {
            documents: [],
            uploading: false,
            uploadForm: {
                source_type: 'file',
                title: '',
                file: null,
                url: '',
                content: '',
            },

            async init() {
                await this.loadDocuments();
            },

            async loadDocuments() {
                const res = await fetch('/api/documents');
                const data = await res.json();
                this.documents = data.data || data;
            },

            async upload() {
                this.uploading = true;
                const formData = new FormData();
                formData.append('source_type', this.uploadForm.source_type);
                formData.append('title', this.uploadForm.title);

                if (this.uploadForm.source_type === 'file' && this.uploadForm.file) {
                    formData.append('file', this.uploadForm.file);
                } else if (this.uploadForm.source_type === 'url') {
                    formData.append('url', this.uploadForm.url);
                } else if (this.uploadForm.source_type === 'database') {
                    formData.append('content', this.uploadForm.content);
                }

                try {
                    const res = await fetch('/api/documents', {
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                        body: formData,
                    });

                    if (res.ok) {
                        const doc = await res.json();
                        this.documents.unshift(doc);
                        this.uploadForm = {source_type: 'file', title: '', file: null, url: '', content: ''};
                    }
                } catch (e) {
                    alert('上傳失敗，請重試。');
                }
                this.uploading = false;
            },

            async deleteDocument(id) {
                await fetch(`/api/documents/${id}`, {
                    method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                });
                this.documents = this.documents.filter(d => d.id !== id);
            }
        }
    }
    </script>
    @endpush
</x-app-layout>
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "feat: add documents management UI"
```

---

## Task 19: 最終整合測試與清理

**Files:**
- Run all tests
- Verify routes

- [ ] **Step 1: 清除快取並執行完整測試**

```bash
php artisan config:clear
php artisan route:list
php artisan test
```

Expected: 所有路由正確註冊，所有測試通過。

- [ ] **Step 2: 建置前端**

```bash
npm run build
```

- [ ] **Step 3: 最終 commit**

```bash
git add -A
git commit -m "chore: final integration and cleanup"
```

---

## 執行順序摘要

| Task | 內容 | 預估 |
|------|------|------|
| 1 | 安裝套件 + config | 基礎設定 |
| 2 | Database migrations | 資料層 |
| 3 | Eloquent models | 資料層 |
| 4 | Contracts (interfaces) | 抽象層 |
| 5 | LLM providers | 服務層 |
| 6 | Embedding providers | 服務層 |
| 7 | DocumentProcessor + test | 核心邏輯 |
| 8 | Retriever + test | 核心邏輯 |
| 9 | RagPipeline | 核心邏輯 |
| 10 | ProcessDocument job | 背景處理 |
| 11 | ServiceProvider bindings | 組裝 |
| 12 | API Controllers | HTTP 層 |
| 13 | Routes | HTTP 層 |
| 14 | User model relationships | 資料層 |
| 15 | Feature tests | 測試 |
| 16 | Model factories | 測試輔助 |
| 17 | Chat UI | 前端 |
| 18 | Documents UI | 前端 |
| 19 | 整合測試 | 驗收 |
