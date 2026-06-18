<?php

namespace Tests\Unit;

use App\Contracts\LlmProvider;
use App\Models\TarotCard;
use App\Models\TarotDeck;
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
        $fallback = TarotSpread::factory()->create(['name' => 'Three Card Spread', 'card_count' => 3]);
        TarotSpread::factory()->create(['name' => 'Single Card', 'card_count' => 1]);

        $this->mockLlm->shouldReceive('chat')->andThrow(new \RuntimeException('LLM error'));

        $result = $this->service->selectSpread('我的感情運勢如何？');

        $this->assertEquals($fallback->id, $result->id);
    }

    public function test_draw_cards_returns_correct_count(): void
    {
        $deck   = TarotDeck::factory()->create(['is_active' => true]);
        $spread = TarotSpread::factory()->create(['card_count' => 3]);
        TarotCard::factory()->count(78)->create(['deck_id' => $deck->id]);

        $result = $this->service->drawCards($spread, $deck->id);

        $this->assertCount(3, $result);
    }

    public function test_draw_cards_returns_no_duplicates(): void
    {
        $deck   = TarotDeck::factory()->create(['is_active' => true]);
        $spread = TarotSpread::factory()->create(['card_count' => 10]);
        TarotCard::factory()->count(78)->create(['deck_id' => $deck->id]);

        $result  = $this->service->drawCards($spread, $deck->id);
        $cardIds = array_column($result, 'card_id');

        $this->assertEquals(count($cardIds), count(array_unique($cardIds)));
    }

    public function test_draw_cards_includes_position_and_reversed_fields(): void
    {
        $deck   = TarotDeck::factory()->create(['is_active' => true]);
        $spread = TarotSpread::factory()->create(['card_count' => 1]);
        TarotCard::factory()->count(78)->create(['deck_id' => $deck->id]);

        $result = $this->service->drawCards($spread, $deck->id);

        $this->assertArrayHasKey('position', $result[0]);
        $this->assertArrayHasKey('card_id', $result[0]);
        $this->assertArrayHasKey('is_reversed', $result[0]);
        $this->assertIsBool($result[0]['is_reversed']);
    }
}
