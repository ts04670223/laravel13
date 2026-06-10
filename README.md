# Laravel RAG AI 系統設計文件

**日期：** 2026-05-19  
**狀態：** 已核准  
**專案：** laravel13

---

## 1. 概述

在 Laravel 13 專案中建立一套 RAG（Retrieval-Augmented Generation）系統，讓已認證的使用者能上傳多種來源的資料（檔案、資料庫內容、網頁），並透過自然語言提問取得基於自有資料的 AI 回答。

### 核心需求

- 混合資料來源：檔案（PDF/TXT/MD/DOCX）、資料庫內容、URL 網頁爬取
- LLM 彈性切換：支援 OpenAI、Anthropic、Ollama（本地模型）
- 介面：REST API + 簡易聊天 UI
- 認證：使用者需登入才能使用
- 向量儲存：PostgreSQL + pgvector

---

## 2. 系統架構

```
使用者 → [Blade + Alpine.js 聊天 UI] → [Laravel API]
                                              │
                    ┌─────────────────────────┼─────────────────────┐
                    │                         │                     │
            [認證 Breeze]            [RAG Pipeline]          [文件管理]
                                          │                        │
                              ┌───────────┼───────────┐            │
                              │           │           │            │
                       [Retriever]   [LLM Service]  [Embedder]     │
                              │           │           │            │
                       [pgvector]    [OpenAI/        [OpenAI/      │
                              │      Anthropic/      Anthropic/    │
                              │      Ollama]         Ollama]       │
                              │                                    │
                    [PostgreSQL Documents Table] ←──────────────────┘
```

### 核心流程

1. **文件匯入流程：** 上傳文件 → Queue Job 拆塊(chunking) → 生成 embedding → 儲存到 pgvector
2. **查詢流程：** 使用者提問 → 問題轉 embedding → pgvector 語義搜尋相似文塊 → 組合 context + 問題 → 送 LLM → 回傳答案（附引用來源）

---

## 3. 資料模型

### `documents` — 原始文件紀錄

| 欄位 | 型別 | 說明 |
|------|------|------|
| id | bigint PK | |
| user_id | bigint FK → users | 上傳者 |
| title | string | 文件名稱 |
| source_type | enum(`file`, `database`, `url`) | 來源類型 |
| source_path | string nullable | 檔案路徑或 URL |
| mime_type | string nullable | 檔案類型 |
| status | enum(`pending`, `processing`, `completed`, `failed`) | 處理狀態 |
| metadata | json nullable | 額外資訊（頁數、字數、錯誤訊息等） |
| created_at / updated_at | timestamps | |

### `document_chunks` — 文件拆塊 + 向量

| 欄位 | 型別 | 說明 |
|------|------|------|
| id | bigint PK | |
| document_id | bigint FK → documents | 所屬文件 |
| content | text | 拆塊後的文字內容 |
| embedding | vector(1536) | pgvector 向量欄位 |
| chunk_index | int | 在原始文件中的順序 |
| metadata | json nullable | 章節標題、頁碼等 |
| created_at / updated_at | timestamps | |

### `conversations` — 對話紀錄

| 欄位 | 型別 | 說明 |
|------|------|------|
| id | bigint PK | |
| user_id | bigint FK → users | |
| title | string nullable | 自動摘要的對話標題 |
| created_at / updated_at | timestamps | |

### `messages` — 對話訊息

| 欄位 | 型別 | 說明 |
|------|------|------|
| id | bigint PK | |
| conversation_id | bigint FK → conversations | |
| role | enum(`user`, `assistant`) | |
| content | text | 訊息內容 |
| sources | json nullable | 引用的 chunk IDs + 片段預覽 + 相似度分數 |
| token_usage | json nullable | `{"prompt": int, "completion": int}` |
| created_at / updated_at | timestamps | |

### 索引

- `document_chunks.embedding`：HNSW 索引（cosine distance）加速向量搜尋
- `documents`：`(user_id, status)` 組合索引
- `conversations`：`(user_id, created_at)` 索引
- `messages`：`(conversation_id, created_at)` 索引

### 向量維度

預設 1536（OpenAI text-embedding-3-small）。透過 `config/ai.php` 的 `embedding_dimensions` 設定，遷移時讀取此值。

---

## 4. Service 層與 LLM 抽象

### 目錄結構

```
app/
├── Contracts/
│   ├── LlmProvider.php
│   └── EmbeddingProvider.php
├── Services/
│   ├── Llm/
│   │   ├── OpenAiProvider.php
│   │   ├── AnthropicProvider.php
│   │   └── OllamaProvider.php
│   ├── Embedding/
│   │   ├── OpenAiEmbedding.php
│   │   ├── AnthropicEmbedding.php
│   │   └── OllamaEmbedding.php
│   ├── DocumentProcessor.php
│   ├── Retriever.php
│   └── RagPipeline.php
├── Jobs/
│   └── ProcessDocument.php
├── Http/Controllers/
│   ├── ChatController.php
│   ├── ConversationController.php
│   └── DocumentController.php
├── Models/
│   ├── Document.php
│   ├── DocumentChunk.php
│   ├── Conversation.php
│   └── Message.php
```

### 核心介面

```php
interface LlmProvider
{
    public function chat(array $messages, array $options = []): string;
    public function stream(array $messages, array $options = []): Generator;
}

interface EmbeddingProvider
{
    public function embed(string $text): array;
    public function embedBatch(array $texts): array;
}
```

### Provider 註冊（AppServiceProvider）

```php
$this->app->bind(LlmProvider::class, fn () => match(config('ai.default_llm')) {
    'openai' => new OpenAiProvider(config('ai.providers.openai')),
    'anthropic' => new AnthropicProvider(config('ai.providers.anthropic')),
    'ollama' => new OllamaProvider(config('ai.providers.ollama')),
});

$this->app->bind(EmbeddingProvider::class, fn () => match(config('ai.default_embedding')) {
    'openai' => new OpenAiEmbedding(config('ai.providers.openai')),
    'anthropic' => new AnthropicEmbedding(config('ai.providers.anthropic')),
    'ollama' => new OllamaEmbedding(config('ai.providers.ollama')),
});
```

---

## 5. 設定檔 (`config/ai.php`)

```php
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

---

## 6. API 端點

所有端點需認證（`auth:sanctum` middleware）。

| Method | URI | 說明 |
|--------|-----|------|
| POST | `/api/documents` | 上傳文件（file/url/database query） |
| GET | `/api/documents` | 列出使用者的文件 |
| DELETE | `/api/documents/{id}` | 刪除文件及其 chunks |
| POST | `/api/conversations` | 建立新對話 |
| GET | `/api/conversations` | 列出對話紀錄 |
| GET | `/api/conversations/{id}` | 取得單一對話（含訊息） |
| DELETE | `/api/conversations/{id}` | 刪除對話 |
| POST | `/api/chat` | 送出問題，取得 AI 回答 |
| POST | `/api/chat/stream` | 串流版本（SSE） |

### `POST /api/chat` Request

```json
{
    "conversation_id": 1,
    "message": "什麼是 Laravel 的 Service Container？",
    "options": {
        "provider": "openai",
        "top_k": 5
    }
}
```

### `POST /api/chat` Response

```json
{
    "message": {
        "id": 42,
        "role": "assistant",
        "content": "Laravel 的 Service Container 是一個...",
        "sources": [
            {
                "chunk_id": 123,
                "document_title": "Laravel 文件.pdf",
                "content_preview": "Service Container 是管理類別依賴...",
                "score": 0.92
            }
        ],
        "token_usage": {"prompt": 850, "completion": 320}
    }
}
```

### `POST /api/chat/stream`

使用 Server-Sent Events（SSE），事件格式：

```
event: chunk
data: {"content": "Laravel"}

event: chunk
data: {"content": " 的 Service"}

event: sources
data: {"sources": [...]}

event: done
data: {"token_usage": {"prompt": 850, "completion": 320}}
```

---

## 7. 前端 UI

### 頁面路由

| URI | 說明 |
|-----|------|
| `/chat` | 聊天主頁面 |
| `/documents` | 文件管理 |

### 聊天頁面（`/chat`）

- 左側：對話列表（新增 / 刪除）
- 右側：聊天訊息區 + 輸入框
- 回答下方顯示引用來源卡片（文件名 + 片段預覽 + 相似度）
- 支援 Markdown 渲染
- 串流輸出：Alpine.js 接 EventSource 逐字顯示
- 技術：Blade + Alpine.js + Tailwind CSS

### 文件管理頁面（`/documents`）

- 上傳檔案（拖放或選取）
- 輸入 URL 爬取
- 顯示處理狀態（pending / processing / completed / failed）
- 刪除文件

---

## 8. 錯誤處理

| 情境 | 處理方式 |
|------|---------|
| LLM API 呼叫失敗 | 重試 2 次（exponential backoff），失敗後回傳友善錯誤訊息 |
| LLM rate limit | 回傳 HTTP 429 + 預計等待時間 |
| 文件處理失敗 | document status → `failed`，錯誤記錄到 metadata，UI 顯示失敗 |
| 向量搜尋無結果 | 仍送 LLM，prompt 說明「找不到相關資料」，AI 據實回答 |
| 不支援的檔案格式 | 上傳時驗證，回傳 HTTP 422 |
| 文件超過大小限制 | 上傳時驗證（預設 10MB），回傳 HTTP 422 |

---

## 9. 支援的文件格式

| 格式 | 解析方式 |
|------|---------|
| PDF | `smalot/pdfparser` |
| TXT / Markdown | 直接讀取 |
| DOCX | `phpoffice/phpword` |
| URL | Laravel HTTP client 爬取 + DOM 文字抽取 |

---

## 10. 測試策略

| 層級 | 範圍 | Mock |
|------|------|------|
| Unit | DocumentProcessor chunking 邏輯、Provider 回應解析、Retriever 排序 | 無外部依賴 |
| Feature | API 端點完整流程、文件上傳、對話 CRUD | `Http::fake()` mock LLM API |
| Integration | 實際 LLM 呼叫（可選） | 用 `.env.testing` 設定，CI 中可跳過 |

---

## 11. 相依套件

```
composer require laravel/breeze pgvector/pgvector smalot/pdfparser phpoffice/phpword
```

- `laravel/breeze`：認證 scaffolding
- `pgvector/pgvector`：pgvector Laravel 整合
- `smalot/pdfparser`：PDF 文字抽取
- `phpoffice/phpword`：DOCX 文字抽取

---

## 12. 部署需求

- PostgreSQL 16+ 加裝 pgvector 擴充（`CREATE EXTENSION vector;`）
- Laravel Queue worker 運行（處理文件 embedding job）
- PHP 8.3+
- 至少一組 LLM API key（OpenAI / Anthropic）或本地 Ollama 服務
- Node.js（Vite 前端建置）

---

## 13. 未來擴展（不在本次範圍）

- 權限控制：文件共享/團隊存取
- 多語言 embedding 模型
- 對話歷史搜尋
- 使用量計費/配額
- 替換為專用向量資料庫（Qdrant）以支援更大規模
