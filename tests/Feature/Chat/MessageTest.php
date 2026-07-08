<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->conversation = Conversation::factory()->create([
            'group_id' => $this->user->group_id
        ]);
        
        // Add the user as a participant to the conversation
        $this->conversation->participants()->attach($this->user->id, [
            'role' => 'participant',
            'joined_at' => now(),
        ]);
    }

    public function test_participant_can_send_message()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('conversations.messages.store', [
            'id' => $this->conversation->id
        ]), [
            'body' => 'Hello, world!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Message sent successfully.');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'body' => 'Hello, world!',
        ]);

        $this->assertDatabaseHas('conversations', [
            'id' => $this->conversation->id,
            'last_activity_at' => now(),
        ]);
    }

    public function test_non_participant_cannot_send_message()
    {
        $nonParticipant = User::factory()->create();

        $this->actingAs($nonParticipant);

        $response = $this->post(route('conversations.messages.store', [
            'id' => $this->conversation->id
        ]), [
            'body' => 'This should fail',
        ]);

        // Non-participants should get 404 (conversation not found due to authorization)
        $response->assertNotFound();
    }

    public function test_message_body_validation()
    {
        $this->actingAs($this->user);

        // Test empty message
        $response = $this->post(route('conversations.messages.store', [
            'id' => $this->conversation->id
        ]), [
            'body' => '',
        ]);

        $response->assertSessionHasErrors('body');

        // Test message too long
        $longMessage = str_repeat('A', 10001); // 10,001 characters
        $response = $this->post(route('conversations.messages.store', [
            'id' => $this->conversation->id
        ]), [
            'body' => $longMessage,
        ]);

        $response->assertSessionHasErrors('body');
    }

    public function test_fetch_messages_for_conversation()
    {
        // Create some messages
        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'body' => 'First message',
        ]);

        $this->actingAs($this->user);

        // Test API endpoint
        $response = $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages");

        $response->assertStatus(200);
        
        // Check that the response contains the expected structure
        $data = $response->decodeResponseJson();
        $this->assertArrayHasKey('data', $data);
        
        // The response should contain a paginated structure
        $responseData = $data['data'];
        $this->assertIsArray($responseData);
    }

    public function test_api_store_message()
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
            'body' => 'API message test',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => [
                         'id',
                         'conversation_id',
                         'sender_id',
                         'body',
                         'created_at',
                         'updated_at'
                     ]
                 ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'body' => 'API message test',
        ]);
    }
}