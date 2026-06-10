<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_conversations(): void
    {
        $this->getJson('/api/conversations')->assertUnauthorized();
    }

    public function test_user_can_list_own_conversations(): void
    {
        $user = User::factory()->create();
        Conversation::factory()->count(2)->create(['user_id' => $user->id]);
        Conversation::factory()->create(); // another user

        $this->actingAs($user)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_create_conversation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/conversations', ['title' => 'Test Chat'])
            ->assertStatus(201)
            ->assertJsonFragment(['title' => 'Test Chat']);
    }

    public function test_user_can_view_own_conversation(): void
    {
        $user = User::factory()->create();
        $conv = Conversation::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson("/api/conversations/{$conv->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $conv->id]);
    }

    public function test_user_cannot_view_other_conversation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conv = Conversation::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->getJson("/api/conversations/{$conv->id}")
            ->assertForbidden();
    }

    public function test_user_can_delete_own_conversation(): void
    {
        $user = User::factory()->create();
        $conv = Conversation::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/conversations/{$conv->id}")
            ->assertOk();

        $this->assertDatabaseMissing('conversations', ['id' => $conv->id]);
    }
}
