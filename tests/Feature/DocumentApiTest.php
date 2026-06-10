<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DocumentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_documents(): void
    {
        $this->getJson('/api/documents')->assertUnauthorized();
    }

    public function test_user_can_list_own_documents(): void
    {
        $user = User::factory()->create();
        Document::factory()->count(3)->create(['user_id' => $user->id]);
        Document::factory()->create(); // another user's doc

        $this->actingAs($user)
            ->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_upload_file_document(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

        $this->actingAs($user)
            ->postJson('/api/documents', [
                'title' => 'Test Document',
                'source_type' => 'file',
                'file' => $file,
            ])
            ->assertStatus(201)
            ->assertJsonFragment(['title' => 'Test Document']);

        $this->assertDatabaseHas('documents', ['title' => 'Test Document', 'user_id' => $user->id]);
    }

    public function test_user_can_create_url_document(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/documents', [
                'title' => 'Web Page',
                'source_type' => 'url',
                'url' => 'https://example.com',
            ])
            ->assertStatus(201);
    }

    public function test_user_can_delete_own_document(): void
    {
        $user = User::factory()->create();
        $doc = Document::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/documents/{$doc->id}")
            ->assertOk();

        $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
    }

    public function test_user_cannot_delete_other_document(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $doc = Document::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->deleteJson("/api/documents/{$doc->id}")
            ->assertForbidden();
    }
}
