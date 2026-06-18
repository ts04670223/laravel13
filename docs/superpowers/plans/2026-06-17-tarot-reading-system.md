# 塔羅牌占卜系統 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在現有 Laravel 13 RAG 聊天機器人中新增塔羅牌占卜系統。用戶輸入問題後 AI 自動選牌陣、系統隨機抽牌、LLM 生成串流解讀，占卜後可繼續追問，整合進現有 Conversation 系統。

**Architecture:** 採用「塔羅作為 Conversation 的一種類型」方案。`conversations.type` 欄位區分 `chat` / `tarot`。占卜初始化由新增的 `TarotController` + `TarotService` 處理，後續追問直接沿用現有 `POST /api/chat/stream`。

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, Blade + Alpine.js + Tailwind CSS, SSE, 現有 LlmProvider Contract

---

## File Structure

### 新建檔案

```
app/Http/Controllers/TarotController.php
app/Models/TarotDeck.php
app/Models/TarotCard.php
app/Models/TarotSpread.php
app/Models/TarotReading.php
app/Policies/TarotReadingPolicy.php
app/Services/TarotService.php

database/migrations/2026_06_17_000001_create_tarot_decks_table.php
database/migrations/2026_06_17_000002_create_tarot_cards_table.php
database/migrations/2026_06_17_000003_create_tarot_spreads_table.php
database/migrations/2026_06_17_000004_create_tarot_readings_table.php
database/migrations/2026_06_17_000005_add_type_to_conversations_table.php
database/seeders/TarotSeeder.php
database/seeders/data/tarot_cards.php
database/seeders/data/tarot_spreads.php

resources/views/tarot/index.blade.php
resources/views/tarot/reading.blade.php
resources/views/tarot/partials/card.blade.php
resources/views/tarot/partials/spread.blade.php

tests/Unit/TarotServiceTest.php
tests/Feature/TarotReadingTest.php
```

### 修改檔案

```
app/Models/Conversation.php              — 加入 type 屬性與 tarotReading relation
app/Providers/AppServiceProvider.php     — 無需修改（TarotService 透過建構式注入）
routes/web.php                           — 新增 /tarot, /tarot/readings 路由群組
routes/api.php                           — 新增 /tarot/readings/{id}/stream 端點
```

---

## Task 1: 資料庫 Migration

**Files:**
- Create: `database/migrations/2026_06_17_000001_create_tarot_decks_table.php`
- Create: `database/migrations/2026_06_17_000002_create_tarot_cards_table.php`
- Create: `database/migrations/2026_06_17_000003_create_tarot_spreads_table.php`
- Create: `database/migrations/2026_06_17_000004_create_tarot_readings_table.php`
- Create: `database/migrations/2026_06_17_000005_add_type_to_conversations_table.php`

- [ ] **Step 1: 建立 tarot_decks migration**

```bash
php artisan make:migration create_tarot_decks_table --path=database/migrations
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
        Schema::create('tarot_decks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarot_decks');
    }
};
```

- [ ] **Step 2: 建立 tarot_cards migration**

```bash
php artisan make:migration create_tarot_cards_table --path=database/migrations
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
        Schema::create('tarot_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deck_id')->constrained('tarot_decks')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_zh');
            $table->enum('arcana', ['major', 'minor']);
            $table->enum('suit', ['wands', 'cups', 'swords', 'pentacles'])->nullable();
            $table->integer('number');
            $table->string('image_path');
            $table->text('upright_meaning');
            $table->text('reversed_meaning');
            $table->json('keywords_upright');
            $table->json('keywords_reversed');
            $table->timestamps();

            $table->index(['deck_id', 'arcana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarot_cards');
    }
};
```

- [ ] **Step 3: 建立 tarot_spreads migration**

```bash
php artisan make:migration create_tarot_spreads_table --path=database/migrations
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
        Schema::create('tarot_spreads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_zh');
            $table->text('description');
            $table->integer('card_count');
            $table->json('positions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarot_spreads');
    }
};
```

- [ ] **Step 4: 建立 tarot_readings migration**

```bash
php artisan make:migration create_tarot_readings_table --path=database/migrations
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
        Schema::create('tarot_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spread_id')->constrained('tarot_spreads');
            $table->text('question');
            $table->enum('reading_style', ['mystic', 'rational']);
            $table->json('drawn_cards');
            $table->text('ai_interpretation')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarot_readings');
    }
};
```

- [ ] **Step 5: 建立 conversations type 欄位 migration**

```bash
php artisan make:migration add_type_to_conversations_table --path=database/migrations
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
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('type', 10)->default('chat')->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
```

- [ ] **Step 6: 執行 migrations**

```bash
php artisan migrate
```

Expected: 5 個新 migration 執行成功，`conversations` 表新增 `type` 欄位。

- [ ] **Step 7: Commit**

```bash
git add database/migrations/
git commit -m "feat(tarot): add database migrations for tarot system"
```

---

## Task 2: Eloquent Models

**Files:**
- Create: `app/Models/TarotDeck.php`
- Create: `app/Models/TarotCard.php`
- Create: `app/Models/TarotSpread.php`
- Create: `app/Models/TarotReading.php`
- Modify: `app/Models/Conversation.php`

- [ ] **Step 1: 建立 TarotDeck model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TarotDeck extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function cards(): HasMany
    {
        return $this->hasMany(TarotCard::class, 'deck_id');
    }
}
```

- [ ] **Step 2: 建立 TarotCard model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarotCard extends Model
{
    protected $fillable = [
        'deck_id', 'name', 'name_zh', 'arcana', 'suit',
        'number', 'image_path', 'upright_meaning',
        'reversed_meaning', 'keywords_upright', 'keywords_reversed',
    ];

    protected $casts = [
        'keywords_upright' => 'array',
        'keywords_reversed' => 'array',
    ];

    public function deck(): BelongsTo
    {
        return $this->belongsTo(TarotDeck::class, 'deck_id');
    }
}
```

- [ ] **Step 3: 建立 TarotSpread model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TarotSpread extends Model
{
    protected $fillable = [
        'name', 'name_zh', 'description', 'card_count', 'positions',
    ];

    protected $casts = ['positions' => 'array'];

    public function readings(): HasMany
    {
        return $this->hasMany(TarotReading::class, 'spread_id');
    }
}
```

- [ ] **Step 4: 建立 TarotReading model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarotReading extends Model
{
    protected $fillable = [
        'user_id', 'conversation_id', 'spread_id',
        'question', 'reading_style', 'drawn_cards', 'ai_interpretation',
    ];

    protected $casts = ['drawn_cards' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function spread(): BelongsTo
    {
        return $this->belongsTo(TarotSpread::class, 'spread_id');
    }
}
```

- [ ] **Step 5: 修改 Conversation model — 加入 type 欄位與關聯**

在 `app/Models/Conversation.php` 的 `$fillable` 加入 `'type'`，並加入 `tarotReading` 關聯：

```php
protected $fillable = [
    'user_id',
    'title',
    'type',   // 新增
];

// 新增以下方法
public function tarotReading(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(TarotReading::class);
}
```

- [ ] **Step 6: Commit**

```bash
git add app/Models/
git commit -m "feat(tarot): add TarotDeck, TarotCard, TarotSpread, TarotReading models"
```

---

## Task 3: TarotService

**Files:**
- Create: `app/Services/TarotService.php`
- Create: `tests/Unit/TarotServiceTest.php`

- [ ] **Step 1: 建立 TarotService 骨架（先讓測試可以引用）**

```php
<?php

namespace App\Services;

use App\Contracts\LlmProvider;
use App\Models\TarotCard;
use App\Models\TarotReading;
use App\Models\TarotSpread;
use Generator;
use Illuminate\Support\Facades\Log;

class TarotService
{
    public function __construct(private LlmProvider $llm) {}

    public function selectSpread(string $question): TarotSpread
    {
        // TODO: implement
    }

    public function drawCards(TarotSpread $spread, int $deckId): array
    {
        // TODO: implement
    }

    public function interpret(TarotReading $reading): Generator
    {
        // TODO: implement
    }

    public function interpretSync(TarotReading $reading): string
    {
        // TODO: implement
    }
}
```

- [ ] **Step 2: 撰寫 TarotServiceTest（Failing Tests）**

```php
<?php

namespace Tests\Unit;

use App\Contracts\LlmProvider;
use App\Models\TarotCard;
use App\Models\TarotDeck;
use App\Models\TarotReading;
use App\Models\TarotSpread;
use App\Services\TarotService;
use Mockery;
use Tests\TestCase;

class TarotServiceTest extends TestCase
{
    private TarotService $service;
    private LlmProvider $mockLlm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLlm = Mockery::mock(LlmProvider::class);
        $this->service = new TarotService($this->mockLlm);
    }

    public function test_select_spread_returns_fallback_when_llm_fails(): void
    {
        // Create a "三牌展開" spread as fallback
        $fallback = TarotSpread::factory()->create(['name' => 'Three Card Spread', 'card_count' => 3]);
        TarotSpread::factory()->create(['name' => 'Single Card', 'card_count' => 1]);

        $this->mockLlm->shouldReceive('chat')->andThrow(new \RuntimeException('LLM error'));

        $result = $this->service->selectSpread('我的感情運勢如何？');

        $this->assertEquals($fallback->id, $result->id);
    }

    public function test_draw_cards_returns_correct_count(): void
    {
        $deck = TarotDeck::factory()->create(['is_active' => true]);
        $spread = TarotSpread::factory()->create(['card_count' => 3]);
        TarotCard::factory()->count(78)->create(['deck_id' => $deck->id]);

        $result = $this->service->drawCards($spread, $deck->id);

        $this->assertCount(3, $result);
    }

    public function test_draw_cards_returns_no_duplicates(): void
    {
        $deck = TarotDeck::factory()->create(['is_active' => true]);
        $spread = TarotSpread::factory()->create(['card_count' => 10]);
        TarotCard::factory()->count(78)->create(['deck_id' => $deck->id]);

        $result = $this->service->drawCards($spread, $deck->id);
        $cardIds = array_column($result, 'card_id');

        $this->assertEquals(count($cardIds), count(array_unique($cardIds)));
    }

    public function test_draw_cards_includes_position_and_reversed_fields(): void
    {
        $deck = TarotDeck::factory()->create(['is_active' => true]);
        $spread = TarotSpread::factory()->create(['card_count' => 1]);
        TarotCard::factory()->count(78)->create(['deck_id' => $deck->id]);

        $result = $this->service->drawCards($spread, $deck->id);

        $this->assertArrayHasKey('position', $result[0]);
        $this->assertArrayHasKey('card_id', $result[0]);
        $this->assertArrayHasKey('is_reversed', $result[0]);
        $this->assertIsBool($result[0]['is_reversed']);
    }
}
```

- [ ] **Step 3: 執行測試確認失敗**

```bash
php artisan test tests/Unit/TarotServiceTest.php
```

Expected: FAIL — 缺少 Factories、方法未實作。

- [ ] **Step 4: 建立 Model Factories**

```bash
php artisan make:factory TarotDeckFactory --model=TarotDeck
php artisan make:factory TarotCardFactory --model=TarotCard
php artisan make:factory TarotSpreadFactory --model=TarotSpread
```

`TarotDeckFactory` 定義：

```php
public function definition(): array
{
    return [
        'name' => $this->faker->words(2, true),
        'description' => $this->faker->sentence(),
        'is_active' => true,
    ];
}
```

`TarotCardFactory` 定義：

```php
public function definition(): array
{
    return [
        'deck_id' => TarotDeck::factory(),
        'name' => $this->faker->words(2, true),
        'name_zh' => $this->faker->words(2, true),
        'arcana' => $this->faker->randomElement(['major', 'minor']),
        'suit' => $this->faker->randomElement(['wands', 'cups', 'swords', 'pentacles', null]),
        'number' => $this->faker->numberBetween(0, 21),
        'image_path' => 'major-00-fool.jpg',
        'upright_meaning' => $this->faker->sentence(),
        'reversed_meaning' => $this->faker->sentence(),
        'keywords_upright' => ['新開始', '冒險'],
        'keywords_reversed' => ['魯莽', '衝動'],
    ];
}
```

`TarotSpreadFactory` 定義：

```php
public function definition(): array
{
    return [
        'name' => 'Three Card Spread',
        'name_zh' => '三牌展開',
        'description' => '過去、現在、未來三個面向',
        'card_count' => 3,
        'positions' => [
            ['index' => 0, 'name' => '過去', 'description' => '影響現在的過去因素'],
            ['index' => 1, 'name' => '現在', 'description' => '當前的處境'],
            ['index' => 2, 'name' => '未來', 'description' => '可能的發展方向'],
        ],
    ];
}
```

- [ ] **Step 5: 實作 TarotService 三個核心方法**

完整實作 `app/Services/TarotService.php`：

```php
<?php

namespace App\Services;

use App\Contracts\LlmProvider;
use App\Models\TarotCard;
use App\Models\TarotReading;
use App\Models\TarotSpread;
use Generator;
use Illuminate\Support\Facades\Log;

class TarotService
{
    public function __construct(private LlmProvider $llm) {}

    public function selectSpread(string $question): TarotSpread
    {
        $spreads = TarotSpread::all(['id', 'name', 'name_zh', 'description', 'card_count']);
        $fallback = $spreads->firstWhere('name', 'Three Card Spread')
            ?? $spreads->first();

        $spreadList = $spreads->map(fn ($s) =>
            "ID:{$s->id} — {$s->name_zh}（{$s->card_count}張）：{$s->description}"
        )->implode("\n");

        $messages = [
            [
                'role' => 'system',
                'content' => '你是塔羅牌師助手。根據用戶問題選擇最適合的牌陣，只回應牌陣的 ID 數字，不要有其他文字。',
            ],
            [
                'role' => 'user',
                'content' => "問題：{$question}\n\n可用牌陣：\n{$spreadList}\n\n請回應最適合的牌陣 ID：",
            ],
        ];

        try {
            $response = trim($this->llm->chat($messages));
            $spreadId = (int) filter_var($response, FILTER_SANITIZE_NUMBER_INT);
            $selected = $spreads->firstWhere('id', $spreadId);
            return $selected ?? $fallback;
        } catch (\Throwable $e) {
            Log::warning('TarotService::selectSpread LLM failed, using fallback', [
                'error' => $e->getMessage(),
                'question' => $question,
            ]);
            return $fallback;
        }
    }

    public function drawCards(TarotSpread $spread, int $deckId): array
    {
        $cards = TarotCard::where('deck_id', $deckId)->get();

        $keys = array_rand($cards->toArray(), $spread->card_count);
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        $drawn = [];
        foreach (array_values($keys) as $position => $key) {
            $drawn[] = [
                'position'    => $position,
                'card_id'     => $cards[$key]->id,
                'is_reversed' => (bool) rand(0, 1),
            ];
        }

        return $drawn;
    }

    public function interpret(TarotReading $reading): Generator
    {
        $messages = $this->buildInterpretMessages($reading);
        return $this->llm->stream($messages);
    }

    public function interpretSync(TarotReading $reading): string
    {
        $messages = $this->buildInterpretMessages($reading);
        return $this->llm->chat($messages);
    }

    private function buildInterpretMessages(TarotReading $reading): array
    {
        $reading->load(['spread', 'drawn_cards_with_cards']);

        $systemPrompt = $reading->reading_style === 'mystic'
            ? "你是一位深諳神秘學的塔羅師，擁有數十年解讀塔羅的智慧。\n用詩意而深邃的語言，以象徵性語言描述命運的訊息與靈魂的旅程。\n語調神秘、充滿洞見，讓求問者感受到宇宙的指引。\n使用繁體中文回答。"
            : "你是一位心理諮詢師，運用塔羅牌作為自我反思與心理投射的工具。\n以理性、有洞察力的方式分析求問者的處境，幫助他們看清問題的本質。\n語調溫和、務實，提供具體可行的思考方向。\n使用繁體中文回答。";

        $spread = $reading->spread;
        $cardLines = $this->buildCardLines($reading);

        $userContent = <<<PROMPT
問題：{$reading->question}
牌陣：{$spread->name_zh}（{$spread->description}）

抽到的牌：
{$cardLines}

請依照牌陣結構，逐一解讀每張牌在其位置的意義，最後給出整體總結。
PROMPT;

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userContent],
        ];
    }

    private function buildCardLines(TarotReading $reading): string
    {
        $spread = $reading->spread;
        $drawnCards = $reading->drawn_cards;
        $cardIds = array_column($drawnCards, 'card_id');
        $cards = TarotCard::whereIn('id', $cardIds)->get()->keyBy('id');

        $lines = [];
        foreach ($drawnCards as $entry) {
            $pos = $spread->positions[$entry['position']] ?? ['name' => '位置' . ($entry['position'] + 1)];
            $card = $cards[$entry['card_id']] ?? null;
            if (!$card) {
                continue;
            }
            $orientation = $entry['is_reversed'] ? '逆位' : '正位';
            $keywords = $entry['is_reversed']
                ? implode('、', $card->keywords_reversed)
                : implode('、', $card->keywords_upright);
            $meaning = $entry['is_reversed'] ? $card->reversed_meaning : $card->upright_meaning;

            $lines[] = "[{$pos['name']}] {$card->name_zh}（{$card->name}）— {$orientation}\n  關鍵字：{$keywords}\n  牌義：{$meaning}";
        }

        return implode("\n\n", $lines);
    }
}
```

- [ ] **Step 6: 執行測試確認通過**

```bash
php artisan test tests/Unit/TarotServiceTest.php
```

Expected: PASS（3 個測試全過）

- [ ] **Step 7: Commit**

```bash
git add app/Services/TarotService.php app/Models/ database/factories/ tests/Unit/TarotServiceTest.php
git commit -m "feat(tarot): implement TarotService with selectSpread, drawCards, interpret"
```

---

## Task 4: Policy 與授權

**Files:**
- Create: `app/Policies/TarotReadingPolicy.php`

- [ ] **Step 1: 建立 TarotReadingPolicy**

```bash
php artisan make:policy TarotReadingPolicy --model=TarotReading
```

編輯內容，只保留 `view` 與 `stream`（即 `update` 改名）方法：

```php
<?php

namespace App\Policies;

use App\Models\TarotReading;
use App\Models\User;

class TarotReadingPolicy
{
    public function view(User $user, TarotReading $reading): bool
    {
        return $user->id === $reading->user_id;
    }

    public function stream(User $user, TarotReading $reading): bool
    {
        return $user->id === $reading->user_id;
    }
}
```

- [ ] **Step 2: 在 AppServiceProvider 或 AuthServiceProvider 註冊 Policy**

Laravel 10+ 自動發現 Policy（檔名對應 Model 名），無需手動在 `AuthServiceProvider` 註冊。確認 `TarotReadingPolicy` 放在 `app/Policies/` 且命名符合慣例即可。

如需手動確認，在 `AppServiceProvider::boot()` 中加入：

```php
use App\Models\TarotReading;
use App\Policies\TarotReadingPolicy;
use Illuminate\Support\Facades\Gate;

// in boot()
Gate::policy(TarotReading::class, TarotReadingPolicy::class);
```

- [ ] **Step 3: Commit**

```bash
git add app/Policies/TarotReadingPolicy.php app/Providers/AppServiceProvider.php
git commit -m "feat(tarot): add TarotReadingPolicy for view and stream authorization"
```

---

## Task 5: TarotController

**Files:**
- Create: `app/Http/Controllers/TarotController.php`

- [ ] **Step 1: 建立 TarotController**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\TarotDeck;
use App\Models\TarotReading;
use App\Services\TarotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TarotController extends Controller
{
    public function __construct(private TarotService $tarotService) {}

    public function index(Request $request): View
    {
        $recentReadings = TarotReading::where('user_id', $request->user()->id)
            ->with('spread')
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        return view('tarot.index', compact('recentReadings'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question'      => 'required|string|max:500',
            'reading_style' => 'required|in:mystic,rational',
        ]);

        $deck = TarotDeck::where('is_active', true)->firstOrFail();

        $spread = $this->tarotService->selectSpread($validated['question']);
        $drawnCards = $this->tarotService->drawCards($spread, $deck->id);

        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'title'   => mb_substr($validated['question'], 0, 50),
            'type'    => 'tarot',
        ]);

        $reading = TarotReading::create([
            'user_id'         => $request->user()->id,
            'conversation_id' => $conversation->id,
            'spread_id'       => $spread->id,
            'question'        => $validated['question'],
            'reading_style'   => $validated['reading_style'],
            'drawn_cards'     => $drawnCards,
        ]);

        return redirect()->route('tarot.reading', $reading);
    }

    public function history(Request $request): View
    {
        $readings = TarotReading::where('user_id', $request->user()->id)
            ->with('spread')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('tarot.history', compact('readings'));
    }

    public function show(Request $request, TarotReading $reading): View
    {
        $this->authorize('view', $reading);

        $reading->load(['spread', 'conversation']);

        $cardIds = array_column($reading->drawn_cards, 'card_id');
        $cards = \App\Models\TarotCard::whereIn('id', $cardIds)->get()->keyBy('id');

        return view('tarot.reading', compact('reading', 'cards'));
    }

    public function stream(Request $request, TarotReading $reading): StreamedResponse
    {
        $this->authorize('stream', $reading);

        return response()->stream(function () use ($reading) {
            try {
                $fullContent = '';

                foreach ($this->tarotService->interpret($reading) as $chunk) {
                    $fullContent .= $chunk;
                    echo "event: chunk\n";
                    echo 'data: ' . json_encode(['content' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                }

                // 串流完成：儲存解讀文字 + 建立第一條 Message
                $reading->update(['ai_interpretation' => $fullContent]);

                \App\Models\Message::create([
                    'conversation_id' => $reading->conversation_id,
                    'role'            => 'assistant',
                    'content'         => $fullContent,
                ]);

                echo "event: done\n";
                echo 'data: ' . json_encode(['type' => 'done']) . "\n\n";
                ob_flush();
                flush();

            } catch (\Throwable $e) {
                echo "event: error\n";
                echo 'data: ' . json_encode(['message' => $e->getMessage()]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type'     => 'text/event-stream',
            'Cache-Control'    => 'no-cache',
            'Connection'       => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/TarotController.php
git commit -m "feat(tarot): add TarotController with store, show, stream actions"
```

---

## Task 6: 路由設定

**Files:**
- Modify: `routes/web.php`
- Modify: `routes/api.php`

- [ ] **Step 1: 更新 routes/web.php**

在現有 `auth` middleware 群組內加入塔羅路由：

```php
use App\Http\Controllers\TarotController;

Route::middleware('auth')->group(function () {
    // ... 現有路由 ...

    // Tarot
    Route::get('/tarot', [TarotController::class, 'index'])->name('tarot.index');
    Route::post('/tarot/readings', [TarotController::class, 'store'])->name('tarot.store');
    Route::get('/tarot/readings', [TarotController::class, 'history'])->name('tarot.history');
    Route::get('/tarot/readings/{reading}', [TarotController::class, 'show'])->name('tarot.reading');
});
```

- [ ] **Step 2: 更新 routes/api.php**

在現有 `auth` middleware 群組內加入串流端點：

```php
use App\Http\Controllers\TarotController;

Route::middleware('auth')->group(function () {
    // ... 現有路由 ...

    // Tarot stream
    Route::post('/tarot/readings/{reading}/stream', [TarotController::class, 'stream']);
});
```

> **注意：** `api.php` 使用 `auth` middleware（session-based），與現有 Chat stream 一致，不需要 Sanctum token。

- [ ] **Step 3: 確認路由清單**

```bash
php artisan route:list --path=tarot
```

Expected: 5 條路由（GET /tarot, POST /tarot/readings, GET /tarot/readings, GET /tarot/readings/{reading}, POST /api/tarot/readings/{reading}/stream）

- [ ] **Step 4: Commit**

```bash
git add routes/web.php routes/api.php
git commit -m "feat(tarot): add web and API routes for tarot system"
```

---

## Task 7: Seeder — 牌組、牌義、牌陣資料

**Files:**
- Create: `database/seeders/data/tarot_cards.php`
- Create: `database/seeders/data/tarot_spreads.php`
- Create: `database/seeders/TarotSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: 建立牌陣資料檔 `database/seeders/data/tarot_spreads.php`**

```php
<?php

return [
    [
        'name'       => 'Single Card',
        'name_zh'    => '單牌指引',
        'description' => '快速獲得一個核心訊息或建議',
        'card_count' => 1,
        'positions'  => [
            ['index' => 0, 'name' => '核心訊息', 'description' => '針對問題的直接指引'],
        ],
    ],
    [
        'name'       => 'Three Card Spread',
        'name_zh'    => '三牌展開',
        'description' => '從過去、現在、未來三個面向解析問題',
        'card_count' => 3,
        'positions'  => [
            ['index' => 0, 'name' => '過去', 'description' => '影響現在的過去因素'],
            ['index' => 1, 'name' => '現在', 'description' => '當前的處境與核心問題'],
            ['index' => 2, 'name' => '未來', 'description' => '可能的發展方向'],
        ],
    ],
    [
        'name'       => 'Five Card Spread',
        'name_zh'    => '五牌展開',
        'description' => '深入分析情況、障礙、建議、潛力與結果',
        'card_count' => 5,
        'positions'  => [
            ['index' => 0, 'name' => '情況', 'description' => '當前整體狀況'],
            ['index' => 1, 'name' => '障礙', 'description' => '面臨的挑戰或阻力'],
            ['index' => 2, 'name' => '建議', 'description' => '推薦的行動方向'],
            ['index' => 3, 'name' => '潛力', 'description' => '尚未發揮的可能性'],
            ['index' => 4, 'name' => '結果', 'description' => '照此路徑可能的結果'],
        ],
    ],
    [
        'name'       => 'Celtic Cross',
        'name_zh'    => '凱爾特十字',
        'description' => '完整深度占卜，涵蓋問題的所有面向',
        'card_count' => 10,
        'positions'  => [
            ['index' => 0, 'name' => '核心',   'description' => '問題的核心本質'],
            ['index' => 1, 'name' => '交叉',   'description' => '阻礙或影響核心的力量'],
            ['index' => 2, 'name' => '根基',   'description' => '潛意識的根源'],
            ['index' => 3, 'name' => '過去',   'description' => '已過去的影響'],
            ['index' => 4, 'name' => '王冠',   'description' => '可能的最佳結果'],
            ['index' => 5, 'name' => '未來',   'description' => '即將到來的影響'],
            ['index' => 6, 'name' => '自我',   'description' => '求問者的狀態與態度'],
            ['index' => 7, 'name' => '環境',   'description' => '外在環境與他人看法'],
            ['index' => 8, 'name' => '希望恐懼', 'description' => '內心的希望或恐懼'],
            ['index' => 9, 'name' => '結果',   'description' => '最終可能的結局'],
        ],
    ],
];
```

- [ ] **Step 2: 建立 78 張牌資料檔 `database/seeders/data/tarot_cards.php`**

此檔案包含 78 張偉特塔羅的中英文牌名、正逆位牌義、關鍵字。由於資料量大（約 500 行），採用分段結構。以下列出前 5 張作為格式範例，完整 78 張需手工補齊：

```php
<?php

// 大阿爾克那 22 張（arcana: major, suit: null, number: 0-21）
// 小阿爾克那 56 張（arcana: minor, suit: wands/cups/swords/pentacles, number: 1-14）
// image_path 命名規則：
//   大阿爾克那 → major-00-fool.jpg, major-01-magician.jpg ...
//   小阿爾克那 → minor-wands-01.jpg, minor-cups-02.jpg ...

return [
    // ── 大阿爾克那 ────────────────────────────────────────
    [
        'name'             => 'The Fool',
        'name_zh'          => '愚者',
        'arcana'           => 'major',
        'suit'             => null,
        'number'           => 0,
        'image_path'       => 'major-00-fool.jpg',
        'upright_meaning'  => '新的開始、自由精神、天真無邪、冒險精神、信任宇宙',
        'reversed_meaning' => '魯莽衝動、缺乏計畫、不負責任、忽視風險',
        'keywords_upright'  => ['新開始', '冒險', '自由', '天真', '信念'],
        'keywords_reversed' => ['魯莽', '衝動', '缺乏計畫', '不負責任'],
    ],
    [
        'name'             => 'The Magician',
        'name_zh'          => '魔術師',
        'arcana'           => 'major',
        'suit'             => null,
        'number'           => 1,
        'image_path'       => 'major-01-magician.jpg',
        'upright_meaning'  => '意志力、技能、創造力、自信、專注、行動力',
        'reversed_meaning' => '操縱、技能未善用、欺騙、優柔寡斷',
        'keywords_upright'  => ['意志力', '技能', '創造', '自信', '行動'],
        'keywords_reversed' => ['操縱', '欺騙', '優柔寡斷', '技能浪費'],
    ],
    [
        'name'             => 'The High Priestess',
        'name_zh'          => '女祭司',
        'arcana'           => 'major',
        'suit'             => null,
        'number'           => 2,
        'image_path'       => 'major-02-high-priestess.jpg',
        'upright_meaning'  => '直覺、神秘、潛意識、內在知識、女性智慧',
        'reversed_meaning' => '秘密、壓抑直覺、資訊隱藏、表面知識',
        'keywords_upright'  => ['直覺', '神秘', '潛意識', '智慧', '靜默'],
        'keywords_reversed' => ['秘密', '壓抑', '隱藏', '表象'],
    ],
    // ... 繼續補齊 19 張大阿爾克那 + 56 張小阿爾克那 ...
];
```

> **說明：** 完整 78 張牌的資料需要逐一填寫，建議分為 4 個區塊：
> 大阿爾克那（22 張）+ 權杖（14 張）+ 聖杯（14 張）+ 寶劍（14 張）+ 錢幣（14 張）

- [ ] **Step 3: 建立 TarotSeeder**

```php
<?php

namespace Database\Seeders;

use App\Models\TarotCard;
use App\Models\TarotDeck;
use App\Models\TarotSpread;
use Illuminate\Database\Seeder;

class TarotSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 建立偉特塔羅牌組
        $deck = TarotDeck::firstOrCreate(
            ['name' => 'Rider-Waite Tarot'],
            [
                'description' => '1909 年由 A.E. Waite 設計，Pamela Coleman Smith 繪圖的經典塔羅牌組。',
                'is_active'   => true,
            ]
        );

        // 2. 寫入 78 張牌
        $cards = require __DIR__ . '/data/tarot_cards.php';
        foreach ($cards as $cardData) {
            TarotCard::firstOrCreate(
                ['deck_id' => $deck->id, 'name' => $cardData['name']],
                array_merge($cardData, ['deck_id' => $deck->id])
            );
        }

        // 3. 寫入牌陣
        $spreads = require __DIR__ . '/data/tarot_spreads.php';
        foreach ($spreads as $spreadData) {
            TarotSpread::firstOrCreate(
                ['name' => $spreadData['name']],
                $spreadData
            );
        }

        $this->command->info('TarotSeeder: ' . count($cards) . ' 張牌、' . count($spreads) . ' 種牌陣已寫入。');
    }
}
```

- [ ] **Step 4: 在 DatabaseSeeder 中引用 TarotSeeder**

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    // 現有內容 ...
    $this->call(TarotSeeder::class);
}
```

- [ ] **Step 5: 執行 Seeder**

```bash
php artisan db:seed --class=TarotSeeder
```

Expected: 輸出 "TarotSeeder: 78 張牌、4 種牌陣已寫入。"

- [ ] **Step 6: Commit**

```bash
git add database/seeders/
git commit -m "feat(tarot): add TarotSeeder with 78 cards and 4 spreads"
```

---

## Task 8: 前端 Blade 視圖

**Files:**
- Create: `resources/views/tarot/index.blade.php`
- Create: `resources/views/tarot/reading.blade.php`
- Create: `resources/views/tarot/partials/card.blade.php`
- Create: `resources/views/tarot/partials/spread.blade.php`
- Create: `resources/views/tarot/history.blade.php`

- [ ] **Step 1: 建立占卜入口頁 `resources/views/tarot/index.blade.php`**

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">🔮 塔羅占卜</h2>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- 占卜表單 --}}
            <div class="lg:col-span-2 bg-white rounded-2xl shadow p-6">
                <form action="{{ route('tarot.store') }}" method="POST" x-data="{ style: 'mystic', loading: false }" @submit="loading = true">
                    @csrf

                    {{-- 風格選擇 --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">解讀風格</label>
                        <div class="flex gap-3">
                            <button type="button"
                                @click="style = 'mystic'"
                                :class="style === 'mystic' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700'"
                                class="flex-1 py-2 px-4 rounded-lg text-sm font-medium transition">
                                🌙 神秘靈性
                            </button>
                            <button type="button"
                                @click="style = 'rational'"
                                :class="style === 'rational' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                                class="flex-1 py-2 px-4 rounded-lg text-sm font-medium transition">
                                🧠 理性分析
                            </button>
                        </div>
                        <input type="hidden" name="reading_style" :value="style">
                    </div>

                    {{-- 問題輸入 --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">你想占卜的問題</label>
                        <textarea name="question" rows="4"
                            class="w-full rounded-lg border-gray-300 focus:ring-purple-500 focus:border-purple-500 text-sm"
                            placeholder="例如：我目前的感情狀況如何？工作上的轉換時機到了嗎？"
                            required maxlength="500"></textarea>
                        @error('question')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                        :disabled="loading"
                        class="w-full py-3 px-6 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition disabled:opacity-50">
                        <span x-show="!loading">✨ 開始占卜</span>
                        <span x-show="loading">🔮 AI 正在選擇牌陣...</span>
                    </button>
                </form>
            </div>

            {{-- 歷史記錄側欄 --}}
            <div class="bg-white rounded-2xl shadow p-6">
                <h3 class="font-semibold text-gray-800 mb-4">最近占卜</h3>
                @forelse ($recentReadings as $r)
                    <a href="{{ route('tarot.reading', $r) }}"
                       class="block py-3 border-b border-gray-100 last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded">
                        <p class="text-sm text-gray-800 truncate">{{ $r->question }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ $r->spread->name_zh }} · {{ $r->created_at->diffForHumans() }}</p>
                    </a>
                @empty
                    <p class="text-sm text-gray-400">尚無占卜記錄</p>
                @endforelse
                @if ($recentReadings->count() >= 10)
                    <a href="{{ route('tarot.history') }}" class="block mt-3 text-sm text-purple-600 hover:underline">查看全部 →</a>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 2: 建立牌卡 partial `resources/views/tarot/partials/card.blade.php`**

```blade
{{-- props: $drawnCard (array), $card (TarotCard model), $position (array), $index (int) --}}
@props(['drawnCard', 'card', 'position', 'index'])

<div x-data="{ flipped: false }"
     class="flex flex-col items-center gap-2"
     x-init="setTimeout(() => flipped = true, {{ $index * 300 + 500 }})">

    {{-- 牌卡翻轉容器 --}}
    <div class="relative w-24 h-40 cursor-pointer" style="perspective: 600px" @click="flipped = !flipped">
        <div class="w-full h-full transition-transform duration-700"
             style="transform-style: preserve-3d"
             :style="flipped ? 'transform: rotateY(0deg)' : 'transform: rotateY(180deg)'">

            {{-- 牌正面 --}}
            <div class="absolute inset-0" style="backface-visibility: hidden">
                <img src="{{ asset('images/tarot/rider-waite/' . $card->image_path) }}"
                     alt="{{ $card->name_zh }}"
                     class="w-full h-full object-cover rounded-lg shadow-md"
                     style="{{ $drawnCard['is_reversed'] ? 'transform: rotate(180deg)' : '' }}">
            </div>

            {{-- 牌背面 --}}
            <div class="absolute inset-0 bg-purple-800 rounded-lg shadow-md flex items-center justify-center"
                 style="backface-visibility: hidden; transform: rotateY(180deg)">
                <span class="text-2xl">🔮</span>
            </div>
        </div>
    </div>

    {{-- 牌名與位置 --}}
    <div class="text-center">
        <p class="text-xs font-medium text-gray-500">{{ $position['name'] }}</p>
        <p class="text-sm font-semibold text-gray-800">{{ $card->name_zh }}</p>
        <p class="text-xs text-gray-400">{{ $drawnCard['is_reversed'] ? '逆位' : '正位' }}</p>
    </div>
</div>
```

- [ ] **Step 3: 建立牌陣排列 partial `resources/views/tarot/partials/spread.blade.php`**

```blade
{{-- props: $reading (TarotReading), $cards (Collection keyed by id) --}}
@props(['reading', 'cards'])

@php
    $spread = $reading->spread;
    $drawn = $reading->drawn_cards;
    $colCount = match($spread->card_count) {
        1  => 'grid-cols-1',
        3  => 'grid-cols-3',
        5  => 'grid-cols-5',
        10 => 'grid-cols-5',
        default => 'grid-cols-3',
    };
@endphp

<div class="grid {{ $colCount }} gap-4 justify-items-center">
    @foreach ($drawn as $index => $drawnCard)
        @php
            $card = $cards[$drawnCard['card_id']] ?? null;
            $position = $spread->positions[$drawnCard['position']] ?? ['name' => '位置' . ($drawnCard['position'] + 1)];
        @endphp
        @if ($card)
            <x-tarot.card
                :drawnCard="$drawnCard"
                :card="$card"
                :position="$position"
                :index="$index"
            />
        @endif
    @endforeach
</div>
```

> **注意：** `<x-tarot.card>` 對應 `resources/views/tarot/partials/card.blade.php`。需在 `AppServiceProvider` 中為 anonymous component 的路徑正確對應，或直接使用 `@include`。

- [ ] **Step 4: 建立占卜結果頁 `resources/views/tarot/reading.blade.php`**

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">🃏 占卜結果</h2>
            <a href="{{ route('tarot.index') }}" class="text-sm text-purple-600 hover:underline">← 重新占卜</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8"
         x-data="tarotReading({{ $reading->id }}, {{ $reading->ai_interpretation ? 'true' : 'false' }})">

        {{-- 問題標題 --}}
        <div class="bg-white rounded-2xl shadow p-6">
            <p class="text-sm text-gray-500 mb-1">{{ $reading->spread->name_zh }} · {{ $reading->reading_style === 'mystic' ? '🌙 神秘靈性' : '🧠 理性分析' }}</p>
            <h3 class="text-lg font-semibold text-gray-800">「{{ $reading->question }}」</h3>
        </div>

        {{-- 牌陣展示 --}}
        <div class="bg-white rounded-2xl shadow p-6">
            <h4 class="font-medium text-gray-700 mb-4">抽到的牌</h4>
            @include('tarot.partials.spread', ['reading' => $reading, 'cards' => $cards])
        </div>

        {{-- AI 解讀串流區 --}}
        <div class="bg-white rounded-2xl shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-medium text-gray-700">AI 解讀</h4>
                <button x-show="streamError" @click="startStream()"
                    class="text-sm text-purple-600 hover:underline">重新生成</button>
            </div>

            {{-- 已有解讀時直接顯示 --}}
            @if ($reading->ai_interpretation)
                <div class="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap">{{ $reading->ai_interpretation }}</div>
            @else
                <div class="min-h-24">
                    <p x-show="!streamStarted && !streamError" class="text-gray-400 text-sm">正在召喚解讀...</p>
                    <p x-show="streamError" class="text-red-500 text-sm">解讀中斷，請點擊「重新生成」</p>
                    <div x-show="streamStarted" class="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap" x-text="streamContent"></div>
                    <span x-show="streaming" class="inline-block w-1 h-4 bg-purple-500 animate-pulse ml-1">|</span>
                </div>
            @endif
        </div>

        {{-- 繼續對話區（解讀完成後顯示） --}}
        <div x-show="streamDone || {{ $reading->ai_interpretation ? 'true' : 'false' }}"
             class="bg-white rounded-2xl shadow p-6">
            <h4 class="font-medium text-gray-700 mb-4">繼續追問</h4>
            <div id="chat-messages" class="space-y-3 mb-4 max-h-80 overflow-y-auto"></div>
            <form @submit.prevent="sendChat" class="flex gap-2">
                <input type="text" x-model="chatInput" placeholder="針對占卜結果繼續提問..."
                    class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-purple-500 focus:border-purple-500">
                <button type="submit" :disabled="chatLoading"
                    class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50">
                    送出
                </button>
            </form>
        </div>

    </div>

    @push('scripts')
    <script>
    function tarotReading(readingId, alreadyInterpreted) {
        return {
            streamContent: '',
            streamStarted: false,
            streamDone: alreadyInterpreted,
            streaming: false,
            streamError: false,
            chatInput: '',
            chatLoading: false,
            conversationId: {{ $reading->conversation_id }},

            init() {
                if (!alreadyInterpreted) {
                    this.startStream();
                }
            },

            async startStream() {
                this.streamError = false;
                this.streamStarted = true;
                this.streaming = true;
                this.streamContent = '';

                try {
                    const res = await fetch('/api/tarot/readings/' + readingId + '/stream', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'text/event-stream',
                        },
                    });

                    const reader = res.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';

                    while (true) {
                        const { value, done } = await reader.read();
                        if (done) break;
                        buffer += decoder.decode(value, { stream: true });

                        const lines = buffer.split('\n');
                        buffer = lines.pop();

                        for (const line of lines) {
                            if (line.startsWith('data: ')) {
                                const data = JSON.parse(line.slice(6));
                                if (data.content) this.streamContent += data.content;
                                if (data.type === 'done') {
                                    this.streaming = false;
                                    this.streamDone = true;
                                }
                            }
                        }
                    }
                } catch (e) {
                    this.streaming = false;
                    this.streamError = true;
                }
            },

            async sendChat() {
                if (!this.chatInput.trim()) return;
                this.chatLoading = true;
                const msg = this.chatInput;
                this.chatInput = '';

                const msgEl = document.createElement('div');
                msgEl.className = 'text-right';
                msgEl.innerHTML = `<span class="bg-purple-100 text-purple-800 text-sm px-3 py-2 rounded-xl inline-block max-w-xs">${msg}</span>`;
                document.getElementById('chat-messages').appendChild(msgEl);

                const replyEl = document.createElement('div');
                replyEl.className = 'text-left';
                replyEl.innerHTML = `<span class="bg-gray-100 text-gray-700 text-sm px-3 py-2 rounded-xl inline-block max-w-xs" id="reply-${Date.now()}"></span>`;
                document.getElementById('chat-messages').appendChild(replyEl);
                const replySpan = replyEl.querySelector('span');

                try {
                    const res = await fetch('/api/chat/stream', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ conversation_id: this.conversationId, message: msg }),
                    });

                    const reader = res.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';

                    while (true) {
                        const { value, done } = await reader.read();
                        if (done) break;
                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop();
                        for (const line of lines) {
                            if (line.startsWith('data: ')) {
                                const data = JSON.parse(line.slice(6));
                                if (data.content) replySpan.textContent += data.content;
                            }
                        }
                    }
                } catch (e) {
                    replySpan.textContent = '發送失敗，請重試';
                }

                this.chatLoading = false;
            }
        }
    }
    </script>
    @endpush
</x-app-layout>
```

- [ ] **Step 5: 建立歷史列表頁 `resources/views/tarot/history.blade.php`**

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">📚 占卜歷史</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-2xl shadow divide-y">
            @forelse ($readings as $r)
                <a href="{{ route('tarot.reading', $r) }}" class="flex items-center gap-4 p-4 hover:bg-gray-50">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $r->question }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ $r->spread->name_zh }} · {{ $r->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                    <span class="text-xs text-gray-400">{{ $r->reading_style === 'mystic' ? '🌙' : '🧠' }}</span>
                </a>
            @empty
                <div class="p-8 text-center text-gray-400">尚無占卜記錄</div>
            @endforelse
        </div>

        <div class="mt-4">{{ $readings->links() }}</div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Commit**

```bash
git add resources/views/tarot/
git commit -m "feat(tarot): add Blade views for tarot index, reading, and history"
```

---

## Task 9: 牌圖資源

**Files:**
- Download: `public/images/tarot/rider-waite/*.jpg`（78 張）

- [ ] **Step 1: 建立目錄**

```bash
mkdir -p public/images/tarot/rider-waite
```

- [ ] **Step 2: 下載偉特塔羅公版圖片**

偉特塔羅（1909 年出版）已進入公共領域，可從 Wikimedia Commons 下載：
https://commons.wikimedia.org/wiki/Rider-Waite_tarot_deck

依照命名規則逐一下載並重命名：

| 規則 | 範例 |
|------|------|
| 大阿爾克那 | `major-00-fool.jpg`、`major-01-magician.jpg` … `major-21-world.jpg` |
| 小阿爾克那 | `minor-wands-01.jpg` … `minor-wands-14.jpg` |
| | `minor-cups-01.jpg` … `minor-cups-14.jpg` |
| | `minor-swords-01.jpg` … `minor-swords-14.jpg` |
| | `minor-pentacles-01.jpg` … `minor-pentacles-14.jpg` |

- [ ] **Step 3: 建立 placeholder 圖片（開發測試用）**

若尚未下載完整圖片，可先用 placeholder：

```bash
# 在 public/images/tarot/ 放一張 card-back.jpg 作為未下載牌的替代
```

在 `card.blade.php` 的 `<img>` 加入 fallback：

```blade
<img ... onerror="this.src='{{ asset('images/tarot/card-back.jpg') }}'">
```

- [ ] **Step 4: Commit**

```bash
git add public/images/tarot/
git commit -m "assets(tarot): add Rider-Waite tarot card images"
```

---

## Task 10: Feature 測試

**Files:**
- Create: `tests/Feature/TarotReadingTest.php`

- [ ] **Step 1: 建立 Feature 測試**

```php
<?php

namespace Tests\Feature;

use App\Models\TarotDeck;
use App\Models\TarotCard;
use App\Models\TarotReading;
use App\Models\TarotSpread;
use App\Models\User;
use App\Services\TarotService;
use Mockery;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TarotReadingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private TarotDeck $deck;
    private TarotSpread $spread;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::factory()->create();
        $this->deck  = TarotDeck::factory()->create(['is_active' => true]);
        $this->spread = TarotSpread::factory()->create([
            'name'       => 'Three Card Spread',
            'card_count' => 3,
        ]);
        TarotCard::factory()->count(78)->create(['deck_id' => $this->deck->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/tarot')->assertRedirect('/login');
        $this->post('/tarot/readings')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_tarot_index(): void
    {
        $this->actingAs($this->user)
            ->get('/tarot')
            ->assertOk()
            ->assertViewIs('tarot.index');
    }

    public function test_store_creates_reading_and_redirects(): void
    {
        // Mock TarotService to avoid LLM call
        $mock = Mockery::mock(TarotService::class);
        $mock->shouldReceive('selectSpread')->once()->andReturn($this->spread);
        $mock->shouldReceive('drawCards')->once()->andReturn([
            ['position' => 0, 'card_id' => 1, 'is_reversed' => false],
            ['position' => 1, 'card_id' => 2, 'is_reversed' => true],
            ['position' => 2, 'card_id' => 3, 'is_reversed' => false],
        ]);
        $this->app->instance(TarotService::class, $mock);

        $response = $this->actingAs($this->user)
            ->post('/tarot/readings', [
                'question'      => '我的感情運勢如何？',
                'reading_style' => 'mystic',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tarot_readings', [
            'user_id'       => $this->user->id,
            'reading_style' => 'mystic',
            'question'      => '我的感情運勢如何？',
        ]);
        $this->assertDatabaseHas('conversations', [
            'user_id' => $this->user->id,
            'type'    => 'tarot',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post('/tarot/readings', [])
            ->assertSessionHasErrors(['question', 'reading_style']);
    }

    public function test_user_cannot_view_another_users_reading(): void
    {
        $otherUser = User::factory()->create();
        $reading = TarotReading::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($this->user)
            ->get("/tarot/readings/{$reading->id}")
            ->assertForbidden();
    }

    public function test_user_can_view_own_reading(): void
    {
        $reading = TarotReading::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get("/tarot/readings/{$reading->id}")
            ->assertOk()
            ->assertViewIs('tarot.reading');
    }
}
```

> **注意：** 需要建立 `TarotReadingFactory`：

```bash
php artisan make:factory TarotReadingFactory --model=TarotReading
```

`TarotReadingFactory` 定義：

```php
public function definition(): array
{
    $spread = TarotSpread::factory()->create(['card_count' => 3]);
    $conversation = \App\Models\Conversation::factory()->create(['type' => 'tarot']);

    return [
        'user_id'         => $conversation->user_id,
        'conversation_id' => $conversation->id,
        'spread_id'       => $spread->id,
        'question'        => fake()->sentence(),
        'reading_style'   => fake()->randomElement(['mystic', 'rational']),
        'drawn_cards'     => [
            ['position' => 0, 'card_id' => 1, 'is_reversed' => false],
        ],
        'ai_interpretation' => null,
    ];
}
```

`ConversationFactory` 需加入 `type` 欄位（修改現有 factory）：

```php
// 在 ConversationFactory::definition() 中加入
'type' => 'chat',
```

- [ ] **Step 2: 執行測試**

```bash
php artisan test tests/Feature/TarotReadingTest.php
```

Expected: 所有測試通過。

- [ ] **Step 3: Commit**

```bash
git add tests/
git commit -m "test(tarot): add unit and feature tests for tarot reading system"
```

---

## Task 11: 導航整合與最終驗收

**Files:**
- Modify: `resources/views/layouts/navigation.blade.php`（加入塔羅入口連結）

- [ ] **Step 1: 在導航列加入塔羅連結**

在現有 navigation.blade.php 的已登入選單中加入：

```blade
<x-nav-link :href="route('tarot.index')" :active="request()->routeIs('tarot.*')">
    🔮 塔羅占卜
</x-nav-link>
```

- [ ] **Step 2: 完整流程手動驗收**

執行以下手動測試流程：

1. 登入後前往 `/tarot`
2. 選擇「神秘靈性」風格，輸入問題「我今年的事業發展如何？」，點擊「開始占卜」
3. 確認頁面跳轉至 `/tarot/readings/{id}`
4. 確認牌卡有翻牌動畫（背面 → 正面）
5. 確認 AI 解讀開始串流顯示（打字機效果）
6. 串流完成後確認追問輸入框出現
7. 輸入「有什麼具體建議嗎？」確認 `/api/chat/stream` 正確回應
8. 前往 `/tarot/readings` 確認歷史列表有紀錄
9. 嘗試存取其他用戶的 reading URL 確認收到 403

- [ ] **Step 3: 執行完整測試套件**

```bash
php artisan test
```

Expected: 所有現有測試 + 新增塔羅測試全部通過。

- [ ] **Step 4: 最終 Commit**

```bash
git add resources/views/layouts/navigation.blade.php
git commit -m "feat(tarot): integrate tarot navigation link"
git tag v1.0-tarot
```

---

## 錯誤處理速查

| 狀況 | 現象 | 解法 |
|------|------|------|
| `TarotDeck::where('is_active', true)->firstOrFail()` 失敗 | 422 錯誤 | 確認已執行 `php artisan db:seed --class=TarotSeeder` |
| LLM 選牌陣超時 | 占卜回應慢 | `selectSpread()` 有 fallback，不影響功能，但需確認 `LOG_LEVEL=warning` 能看到 log |
| 串流 SSE 沒輸出 | 結果頁面空白 | 確認 `php-fpm` / `nginx` 未緩衝回應，`.htaccess` 或 nginx config 加 `X-Accel-Buffering: no` |
| 翻牌動畫不觸發 | 牌卡全部正面 | 確認 Alpine.js 已正確載入（Breeze 預設已包含） |
| 追問沒回應 | 對話框送出後無反應 | 確認 `conversations.type` migration 已執行，`conversation_id` 正確傳入 |

---

## 實作順序總覽

```
Task 1  資料庫 Migration（5 個）
Task 2  Eloquent Models（4 個 + 修改 Conversation）
Task 3  TarotService（核心邏輯）
Task 4  Policy（授權）
Task 5  TarotController
Task 6  路由（web.php + api.php）
Task 7  Seeder（牌義資料）
Task 8  Blade 視圖（4 個）
Task 9  牌圖資源
Task 10 測試
Task 11 導航整合 + 驗收
```

每個 Task 完成後執行 `php artisan test` 確保無 regression，再 commit。
