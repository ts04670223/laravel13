<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\TarotCard;
use App\Models\TarotDeck;
use App\Models\TarotReading;
use App\Models\TarotSpread;
use App\Models\User;
use App\Services\TarotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TarotReadingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private TarotDeck $deck;
    private TarotSpread $spread;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user   = User::factory()->create();
        $this->deck   = TarotDeck::factory()->create(['is_active' => true]);
        $this->spread = TarotSpread::factory()->create([
            'name'       => 'Three Card Spread',
            'card_count' => 3,
        ]);
        TarotCard::factory()->count(78)->create(['deck_id' => $this->deck->id]);
    }

    // ── 訪客保護 ────────────────────────────────────────────

    public function test_guest_is_redirected_from_tarot_index(): void
    {
        $this->get('/tarot')->assertRedirect('/login');
    }

    public function test_guest_is_redirected_from_tarot_store(): void
    {
        $this->post('/tarot/readings')->assertRedirect('/login');
    }

    public function test_guest_is_redirected_from_tarot_history(): void
    {
        $this->get('/tarot/readings')->assertRedirect('/login');
    }

    // ── 正常頁面渲染 ─────────────────────────────────────────

    public function test_authenticated_user_can_view_tarot_index(): void
    {
        $this->actingAs($this->user)
            ->get('/tarot')
            ->assertOk()
            ->assertViewIs('tarot.index');
    }

    public function test_authenticated_user_can_view_history(): void
    {
        $this->actingAs($this->user)
            ->get('/tarot/readings')
            ->assertOk()
            ->assertViewIs('tarot.history');
    }

    // ── 建立占卜 ─────────────────────────────────────────────

    public function test_store_creates_reading_conversation_and_redirects(): void
    {
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

    // ── 表單驗證 ─────────────────────────────────────────────

    public function test_store_requires_question(): void
    {
        $this->actingAs($this->user)
            ->post('/tarot/readings', ['reading_style' => 'mystic'])
            ->assertSessionHasErrors('question');
    }

    public function test_store_requires_reading_style(): void
    {
        $this->actingAs($this->user)
            ->post('/tarot/readings', ['question' => '測試問題'])
            ->assertSessionHasErrors('reading_style');
    }

    public function test_store_rejects_invalid_reading_style(): void
    {
        $this->actingAs($this->user)
            ->post('/tarot/readings', [
                'question'      => '測試問題',
                'reading_style' => 'invalid',
            ])
            ->assertSessionHasErrors('reading_style');
    }

    public function test_store_rejects_question_exceeding_500_chars(): void
    {
        $this->actingAs($this->user)
            ->post('/tarot/readings', [
                'question'      => str_repeat('問', 501),
                'reading_style' => 'mystic',
            ])
            ->assertSessionHasErrors('question');
    }

    // ── Policy 授權 ──────────────────────────────────────────

    public function test_user_can_view_own_reading(): void
    {
        $reading = TarotReading::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->get("/tarot/readings/{$reading->id}")
            ->assertOk()
            ->assertViewIs('tarot.reading');
    }

    public function test_user_cannot_view_another_users_reading(): void
    {
        $other   = User::factory()->create();
        $reading = TarotReading::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->get("/tarot/readings/{$reading->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_stream_another_users_reading(): void
    {
        $other   = User::factory()->create();
        $reading = TarotReading::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->post("/api/tarot/readings/{$reading->id}/stream")
            ->assertForbidden();
    }
}
