# 塔羅牌占卜系統設計文件

**日期：** 2026-06-17  
**專案：** Laravel 13 AI 知識助手  
**功能：** 塔羅牌占卜系統

---

## 一、概述

在現有的 Laravel RAG 聊天機器人中新增塔羅牌占卜系統。用戶輸入問題後，AI 自動選擇最適合的牌陣，系統隨機抽牌，LLM 根據牌義生成占卜解讀，占卜完成後可繼續追問 AI，整合進現有的對話系統。

### 核心需求

- **混合式占卜**：用戶輸入問題，AI 自動選擇牌陣（不需用戶手動選）
- **資料庫管理牌義**：78 張牌存入資料庫，支援多牌組擴充
- **對話延伸**：占卜完成後可繼續追問，整合現有 Conversation 系統
- **真實牌圖**：使用偉特塔羅公版圖片
- **風格可切換**：神秘靈性風 / 理性分析風，用戶占卜前選擇

---

## 二、架構方案

採用**方案 B：塔羅作為 Conversation 的一種類型**。

在現有 `Conversation` 模型加入 `type` 欄位，占卜是啟動對話的特殊初始化流程，之後的追問完全沿用現有 `ChatController` + `RagPipeline`，最大化複用現有架構，最小化新增代碼量。

---

## 三、資料層

### 新增資料表

#### `tarot_decks` — 牌組

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | bigint PK | |
| name | string | 牌組名稱（英文） |
| description | text nullable | 牌組描述 |
| is_active | boolean default true | 是否啟用 |
| timestamps | | |

#### `tarot_cards` — 塔羅牌（78 張）

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | bigint PK | |
| deck_id | FK → tarot_decks | |
| name | string | 牌名（英文） |
| name_zh | string | 牌名（中文） |
| arcana | enum('major','minor') | 大阿爾克那 / 小阿爾克那 |
| suit | enum('wands','cups','swords','pentacles') nullable | 小阿爾克那花色，大阿爾克那為 null |
| number | integer | 牌號（大阿爾克那 0-21，小阿爾克那 1-14） |
| image_path | string | 牌圖路徑（相對於 public/images/tarot/） |
| upright_meaning | text | 正位牌義 |
| reversed_meaning | text | 逆位牌義 |
| keywords_upright | json | 正位關鍵字陣列 |
| keywords_reversed | json | 逆位關鍵字陣列 |
| timestamps | | |

#### `tarot_spreads` — 牌陣定義

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | bigint PK | |
| name | string | 牌陣名稱（英文） |
| name_zh | string | 牌陣名稱（中文） |
| description | text | 牌陣說明 |
| card_count | integer | 需要幾張牌 |
| positions | json | 各位置定義（見下方範例） |
| timestamps | | |

`positions` JSON 結構範例：
```json
[
  {"index": 0, "name": "過去", "description": "代表影響現在的過去因素"},
  {"index": 1, "name": "現在", "description": "代表當前的處境與核心問題"},
  {"index": 2, "name": "未來", "description": "代表可能的發展方向"}
]
```

預載牌陣：
- **單牌**（1 張）：快速指引
- **三牌展開**（3 張）：過去 / 現在 / 未來
- **五牌**（5 張）：情況 / 障礙 / 建議 / 潛力 / 結果
- **凱爾特十字**（10 張）：完整深度占卜

#### `tarot_readings` — 占卜記錄

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | bigint PK | |
| user_id | FK → users | |
| conversation_id | FK → conversations | 關聯對話（用於追問） |
| spread_id | FK → tarot_spreads | 使用的牌陣 |
| question | text | 用戶的問題 |
| reading_style | enum('mystic','rational') | 解讀風格 |
| drawn_cards | json | 抽牌結果（見下方範例） |
| ai_interpretation | text nullable | AI 生成的完整解讀文字 |
| timestamps | | |

`drawn_cards` JSON 結構範例：
```json
[
  {"position": 0, "card_id": 12, "is_reversed": false},
  {"position": 1, "card_id": 47, "is_reversed": true},
  {"position": 2, "card_id": 3,  "is_reversed": false}
]
```

### 現有資料表修改

**`conversations`** 新增欄位：
```sql
ALTER TABLE conversations ADD COLUMN type VARCHAR(10) NOT NULL DEFAULT 'chat';
-- enum: 'chat' | 'tarot'
```

---

## 四、服務層

### `TarotService`

核心業務邏輯，注入 `LlmProvider`（沿用現有合約），提供三個公開方法：

#### `selectSpread(string $question): TarotSpread`

- 從資料庫讀取所有可用牌陣清單（名稱、描述、張數）
- 組成 prompt 請 LLM 根據問題選擇最適合的牌陣
- 解析 LLM 回應取得牌陣 ID
- **Fallback**：若 LLM 呼叫失敗或回應無法解析，自動選擇「三牌展開」

#### `drawCards(TarotSpread $spread, int $deckId): array`

- `$deckId` 由 Controller 傳入，Controller 從 `tarot_decks` 查詢第一個 `is_active = true` 的牌組取得
- 從指定牌組查詢所有牌（78 張）
- PHP `array_rand()` 隨機抽取 `$spread->card_count` 張不重複的牌
- 每張牌以 `rand(0, 1)` 決定正位或逆位
- 回傳符合 `drawn_cards` JSON 格式的陣列

#### `interpret(TarotReading $reading): Generator`

- 根據 `reading_style` 選擇 system prompt（見下方 Prompt 設計）
- 建構包含問題、牌陣說明、每張牌位置+牌名+正逆位+關鍵字的完整 prompt
- 呼叫 `LlmProvider::stream()` 串流回傳解讀
- 非串流版本 `interpretSync()` 供測試使用

### Prompt 設計

**Mystic 風格（神秘靈性）：**
```
你是一位深諳神秘學的塔羅師，擁有數十年解讀塔羅的智慧。
用詩意而深邃的語言，以象徵性語言描述命運的訊息與靈魂的旅程。
語調神秘、充滿洞見，讓求問者感受到宇宙的指引。
使用繁體中文回答。
```

**Rational 風格（理性分析）：**
```
你是一位心理諮詢師，運用塔羅牌作為自我反思與心理投射的工具。
以理性、有洞察力的方式分析求問者的處境，幫助他們看清問題的本質。
語調溫和、務實，提供具體可行的思考方向。
使用繁體中文回答。
```

兩種 prompt 都會附加：
```
問題：{question}
牌陣：{spread.name_zh}（{spread.description}）

抽到的牌：
[位置一：{position.name}] {card.name_zh}（{card.name}）— {正位/逆位}
  關鍵字：{keywords}
  牌義：{upright/reversed_meaning}
...

請依照牌陣結構，逐一解讀每張牌在其位置的意義，最後給出整體總結。
```

### `TarotController`

所有路由都在 `auth` middleware 下，使用 `TarotReadingPolicy` 確保用戶只能存取自己的記錄。

| 方法 | 路由 | 說明 |
|------|------|------|
| GET | `/tarot` | 占卜入口頁面 |
| POST | `/tarot/readings` | 建立新占卜（選牌陣、抽牌、建 Conversation） |
| GET | `/tarot/readings` | 歷史占卜列表 |
| GET | `/tarot/readings/{reading}` | 占卜結果頁面 |
| POST | `/tarot/readings/{reading}/stream` | 串流生成初始 AI 解讀 |

`POST /tarot/readings` 流程：
1. 驗證輸入（question 必填，reading_style 必填）
2. 呼叫 `TarotService::selectSpread()`
3. 呼叫 `TarotService::drawCards()`
4. 建立 `Conversation(type='tarot')`
5. 建立 `TarotReading`（含 drawn_cards，ai_interpretation 暫為 null）
6. 回傳 redirect 至 `/tarot/readings/{id}`

後續追問直接使用現有 `POST /api/chat/stream`，傳入 `conversation_id`，不需要新的 API endpoint。

> **路由位置說明：** `/tarot`、`/tarot/readings`、`/tarot/readings/{id}` 這三條 GET/POST 放在 `routes/web.php`（回傳 Blade view 或 redirect）。`/tarot/readings/{reading}/stream` 是串流端點，放在 `routes/api.php`（回傳純文字串流，不需要 session/CSRF，但仍需 `auth:sanctum` middleware）。

---

## 五、前端 UI

### 技術棧

Blade 模板 + Alpine.js + Tailwind CSS（與現有專案一致，不引入新依賴）

### 頁面一：`/tarot`（占卜入口）

```
┌─────────────────────────────────┬──────────────────┐
│  🔮 塔羅占卜                     │  歷史占卜記錄     │
│                                 │                  │
│  解讀風格：[神秘靈性] [理性分析]  │  • 2026-06-17    │
│                                 │    我的感情...    │
│  ┌─────────────────────────┐    │                  │
│  │  請輸入你想占卜的問題...  │    │  • 2026-06-15    │
│  │                         │    │    事業方向...    │
│  └─────────────────────────┘    │                  │
│                                 │                  │
│  [  開始占卜  ]                  │                  │
└─────────────────────────────────┴──────────────────┘
```

### 頁面二：`/tarot/readings/{id}`（占卜結果）

**上方 — 牌陣展示區：**
- 根據 `spread.positions` 動態排列牌卡
- 每張牌顯示：偉特塔羅圖片（逆位旋轉 180°）、牌名（中/英）、位置名稱
- Alpine.js 控制翻牌動畫（背面 → 正面 CSS transition）

**中間 — AI 解讀串流區：**
- 頁面載入後自動觸發 `POST /tarot/readings/{id}/stream`
- Alpine.js 使用 `fetch` + `ReadableStream` 逐字顯示文字（打字機效果）
- 串流中斷時顯示「重新生成」按鈕

**下方 — 繼續對話區：**
- 複用現有 `/chat` 頁面的對話 UI 元件
- 綁定 `conversation_id` = 此次占卜的 conversation

### 牌圖資源

- 來源：[Wikimedia Commons - Rider-Waite tarot deck](https://commons.wikimedia.org/wiki/Rider-Waite_tarot_deck)（1909 年出版，公共領域）
- 存放位置：`public/images/tarot/rider-waite/`
- 命名規則：`major-00-fool.jpg`、`minor-wands-01.jpg` 等
- `tarot_cards.image_path` 存相對路徑，前端用 `asset()` helper 生成完整 URL

---

## 六、資料流

```
用戶輸入問題 + 選風格（mystic/rational）
        ↓
POST /tarot/readings
        ↓
TarotService::selectSpread(question)
  → LLM 分析問題選牌陣
  → Fallback: 三牌展開
        ↓
TarotService::drawCards(spread, deckId)
  → 隨機抽牌 + 決定正逆位
        ↓
建立 Conversation(type='tarot')
建立 TarotReading(drawn_cards, spread_id, conversation_id)
        ↓
Redirect → GET /tarot/readings/{id}
        ↓
前端自動觸發 POST /tarot/readings/{id}/stream
        ↓
TarotService::interpret(reading)
  → 組 prompt（問題 + 牌陣 + 牌義）
  → LlmProvider::stream()
        ↓
SSE 串流回前端，逐字顯示解讀文字
        ↓
串流完成：
  - 更新 tarot_readings.ai_interpretation
  - 建立第一條 Message(role='assistant') 到 Conversation
        ↓
顯示對話輸入框
        ↓
用戶追問 → POST /api/chat/stream（現有 API，傳 conversation_id）
```

---

## 七、錯誤處理

| 情境 | 處理方式 |
|------|----------|
| LLM 選牌陣失敗 | Fallback 到「三牌展開」，記錄 warning log |
| 串流中斷 | 前端顯示「解讀中斷」提示 + 「重新生成」按鈕，重新呼叫 stream endpoint |
| 牌組資料不存在 | 回傳 422，前端提示「系統資料尚未初始化」 |
| 用戶未登入 | `auth` middleware 攔截，跳轉登入頁 |
| 存取他人記錄 | `TarotReadingPolicy` 回傳 403 |

---

## 八、權限

- 全部塔羅路由（`/tarot/*`）加入 `auth` middleware
- `TarotReadingPolicy`：`view`、`stream` 只允許 `tarot_reading.user_id === auth()->id()`
- `Conversation` 沿用現有的 Policy（`ConversationPolicy`）

---

## 九、Seeder

`TarotSeeder` 預載：

1. **偉特塔羅牌組**（`tarot_decks`）：一副，`is_active = true`
2. **78 張牌**（`tarot_cards`）：
   - 大阿爾克那 22 張（0 愚者 → 21 世界）
   - 小阿爾克那 56 張（權杖/聖杯/寶劍/錢幣各 14 張）
   - 每張含中英文牌名、正逆位牌義、關鍵字陣列、image_path
3. **4 種牌陣**（`tarot_spreads`）：
   - 單牌（1 張）
   - 三牌展開（3 張）：過去 / 現在 / 未來
   - 五牌（5 張）：情況 / 障礙 / 建議 / 潛力 / 結果
   - 凱爾特十字（10 張）：完整深度占卜

---

## 十、新增檔案清單

```
app/
  Http/Controllers/TarotController.php
  Models/TarotDeck.php
  Models/TarotCard.php
  Models/TarotSpread.php
  Models/TarotReading.php
  Policies/TarotReadingPolicy.php
  Services/TarotService.php

database/
  migrations/
    xxxx_create_tarot_decks_table.php
    xxxx_create_tarot_cards_table.php
    xxxx_create_tarot_spreads_table.php
    xxxx_create_tarot_readings_table.php
    xxxx_add_type_to_conversations_table.php
  seeders/
    TarotSeeder.php
    data/
      tarot_cards.php   (78 張牌資料)
      tarot_spreads.php (4 種牌陣資料)

resources/views/tarot/
  index.blade.php       (占卜入口頁)
  reading.blade.php     (占卜結果頁)
  partials/
    card.blade.php      (單張牌卡元件)
    spread.blade.php    (牌陣排列元件)

public/images/tarot/
  rider-waite/
    major-00-fool.jpg
    major-01-magician.jpg
    ... (78 張)

routes/
  web.php               (新增 /tarot, /tarot/readings GET/POST 路由群組)
  api.php               (新增 /tarot/readings/{id}/stream 串流端點)
```

---

## 十一、不在範圍內（YAGNI）

- 管理後台（新增/編輯牌義）— 直接用 Seeder 或 Tinker 管理
- 多語言支援（英文 UI）— 目前只需繁體中文
- 社群分享功能（分享占卜結果）
- 付費限制（每日占卜次數上限）
- 第二副牌組 — 架構支援，但初期只實作偉特塔羅
