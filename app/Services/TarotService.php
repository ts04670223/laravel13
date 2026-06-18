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
        $spreads  = TarotSpread::all(['id', 'name', 'name_zh', 'description', 'card_count']);
        $fallback = $spreads->firstWhere('name', 'Three Card Spread') ?? $spreads->first();

        $spreadList = $spreads->map(fn ($s) =>
            "ID:{$s->id} — {$s->name_zh}（{$s->card_count}張）：{$s->description}"
        )->implode("\n");

        $messages = [
            [
                'role'    => 'system',
                'content' => '你是塔羅牌師助手。根據用戶問題選擇最適合的牌陣，只回應牌陣的 ID 數字，不要有其他文字。',
            ],
            [
                'role'    => 'user',
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
                'error'    => $e->getMessage(),
                'question' => $question,
            ]);

            return $fallback;
        }
    }

    public function drawCards(TarotSpread $spread, int $deckId): array
    {
        $cards = TarotCard::where('deck_id', $deckId)->get();

        $keys = array_rand($cards->toArray(), $spread->card_count);
        if (! is_array($keys)) {
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
        $reading->load('spread');

        $systemPrompt = $reading->reading_style === 'mystic'
            ? "你是一位深諳神秘學的塔羅師，擁有數十年解讀塔羅的智慧。\n用詩意而深邃的語言，以象徵性語言描述命運的訊息與靈魂的旅程。\n語調神秘、充滿洞見，讓求問者感受到宇宙的指引。\n使用繁體中文回答。"
            : "你是一位心理諮詢師，運用塔羅牌作為自我反思與心理投射的工具。\n以理性、有洞察力的方式分析求問者的處境，幫助他們看清問題的本質。\n語調溫和、務實，提供具體可行的思考方向。\n使用繁體中文回答。";

        $spread    = $reading->spread;
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
        $spread     = $reading->spread;
        $drawnCards = $reading->drawn_cards;
        $cardIds    = array_column($drawnCards, 'card_id');
        $cards      = TarotCard::whereIn('id', $cardIds)->get()->keyBy('id');

        $lines = [];
        foreach ($drawnCards as $entry) {
            $pos  = $spread->positions[$entry['position']] ?? ['name' => '位置' . ($entry['position'] + 1)];
            $card = $cards[$entry['card_id']] ?? null;

            if (! $card) {
                continue;
            }

            $orientation = $entry['is_reversed'] ? '逆位' : '正位';
            $keywords    = $entry['is_reversed']
                ? implode('、', $card->keywords_reversed)
                : implode('、', $card->keywords_upright);
            $meaning = $entry['is_reversed'] ? $card->reversed_meaning : $card->upright_meaning;

            $lines[] = "[{$pos['name']}] {$card->name_zh}（{$card->name}）— {$orientation}\n  關鍵字：{$keywords}\n  牌義：{$meaning}";
        }

        return implode("\n\n", $lines);
    }
}
