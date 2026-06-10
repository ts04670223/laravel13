<?php

namespace Tests\Feature;

use App\Contracts\EmbeddingProvider;
use App\Contracts\LlmProvider;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_chat(): void
    {
        $this->postJson('/api/chat', ['message' => 'hello'])->assertUnauthorized();
    }

    public function test_chat_requires_conversation_and_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/chat', [])
            ->assertUnprocessable();
    }

    public function test_user_can_chat(): void
    {
        $user = User::factory()->create();
        $conv = Conversation::factory()->create(['user_id' => $user->id]);

        // Mock the LLM provider
        $mock = \Mockery::mock(LlmProvider::class);
        $mock->shouldReceive('chat')->andReturn('Hello! How can I help?');
        $this->app->instance(LlmProvider::class, $mock);

        // Mock the Embedding provider
        $embeddingMock = \Mockery::mock(EmbeddingProvider::class);
        $embeddingMock->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.0));
        $this->app->instance(EmbeddingProvider::class, $embeddingMock);

        $this->actingAs($user)
            ->postJson('/api/chat', [
                'conversation_id' => $conv->id,
                'message' => 'Hi there',
            ])
            ->assertOk()
            ->assertJsonStructure(['message' => ['id', 'content', 'sources']]);
    }
}
