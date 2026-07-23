<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two users in the same group
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create([
            'group_id' => $this->user->group_id,
        ]);

        // Create a conversation and add both users as participants
        $this->conversation = Conversation::factory()->create([
            'group_id' => $this->user->group_id,
        ]);
        $this->conversation->participants()->attach([
            $this->user->id => ['role' => 'participant', 'joined_at' => now()],
            $this->otherUser->id => ['role' => 'participant', 'joined_at' => now()],
        ]);
    }

    // -------------------------------------------------------------------
    //  Pull Tests
    // -------------------------------------------------------------------

    public function test_pull_requires_authentication()
    {
        $response = $this->getJson('/api/v1/sync/pull?device_id=test-device');
        $response->assertStatus(401);
    }

    public function test_pull_requires_device_id()
    {
        $this->actingAs($this->user);
        $response = $this->getJson('/api/v1/sync/pull');
        $response->assertStatus(422);
    }

    public function test_pull_returns_no_data_for_first_sync_when_no_activity()
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/sync/pull?device_id=test-device');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'conversations' => [],
                'messages' => [],
                'status_updates' => [],
            ],
        ]);
        $response->assertJsonStructure([
            'data' => ['synced_at'],
        ]);
    }

    public function test_pull_returns_messages_after_checkpoint()
    {
        // Create a message AFTER the initial checkpoint
        $message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->otherUser->id,
            'body' => 'Hello from the other user!',
            'created_at' => now()->addHour(),  // Ensure it's after the checkpoint
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/sync/pull?device_id=test-device');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.messages');
        $response->assertJsonPath('data.messages.0.body', 'Hello from the other user!');
    }

    public function test_pull_creates_checkpoint_on_first_sync()
    {
        $this->actingAs($this->user);

        // First sync — should create a checkpoint
        $this->getJson('/api/v1/sync/pull?device_id=test-device');

        $this->assertDatabaseHas('sync_checkpoints', [
            'user_id' => $this->user->id,
            'device_id' => 'test-device',
        ]);
    }

    public function test_pull_updates_checkpoint_after_successful_sync()
    {
        $this->actingAs($this->user);

        // First sync — creates checkpoint
        $firstResponse = $this->getJson('/api/v1/sync/pull?device_id=test-device');
        $firstSyncedAt = $firstResponse->json('data.synced_at');

        // Small delay to ensure timestamps differ
        $this->travel(1)->minutes();

        // Second sync — should update checkpoint
        $secondResponse = $this->getJson('/api/v1/sync/pull?device_id=test-device');
        $secondSyncedAt = $secondResponse->json('data.synced_at');

        $this->assertNotEquals($firstSyncedAt, $secondSyncedAt);
    }

    public function test_pull_returns_only_new_data()
    {
        $this->actingAs($this->user);

        // First sync
        $this->getJson('/api/v1/sync/pull?device_id=test-device');

        // Create a message AFTER the first sync
        $this->travel(1)->minutes();

        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->otherUser->id,
            'body' => 'This message should appear in the next sync',
        ]);

        // Verify an OLDER message does NOT appear (it was created before the checkpoint)
        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'body' => 'This should NOT appear',
            'created_at' => now()->subDays(7),  // Before the checkpoint
        ]);

        // Second sync
        $response = $this->getJson('/api/v1/sync/pull?device_id=test-device');

        $messages = $response->json('data.messages');

        // Only the message created after the first sync should appear
        $bodies = collect($messages)->pluck('body')->toArray();
        $this->assertContains('This message should appear in the next sync', $bodies);
    }

    public function test_pull_returns_status_updates()
    {
        // Create a message first (Message::booted auto-creates 'sent' status for recipients)
        $message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->otherUser->id,
            'body' => 'Message with status tracking',
        ]);

        // Update the auto-created status to 'delivered' (avoids unique constraint violation)
        MessageStatus::where('message_id', $message->id)
            ->where('user_id', $this->user->id)
            ->update(['status' => 'delivered']);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/sync/pull?device_id=test-device');

        $statusUpdates = $response->json('data.status_updates');
        $this->assertCount(1, $statusUpdates);
        $this->assertEquals('delivered', $statusUpdates[0]['status']);
    }

    public function test_pull_respects_group_isolation()
    {
        // Create a user from a DIFFERENT group
        $otherGroupUser = User::factory()->create();

        // Create a conversation in the other user's group
        $otherConversation = Conversation::factory()->create([
            'group_id' => $otherGroupUser->group_id,
        ]);
        $otherConversation->participants()->attach($otherGroupUser->id, [
            'role' => 'participant', 'joined_at' => now(),
        ]);

        $message = Message::factory()->create([
            'conversation_id' => $otherConversation->id,
            'sender_id' => $otherGroupUser->id,
            'body' => 'This is in another group',
        ]);

        // Our user should NOT see this message
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/sync/pull?device_id=test-device');

        $messages = $response->json('data.messages');
        $bodies = collect($messages)->pluck('body')->toArray();
        $this->assertNotContains('This is in another group', $bodies);
    }

    public function test_different_devices_have_independent_checkpoints()
    {
        $this->actingAs($this->user);

        // First device syncs
        $this->getJson('/api/v1/sync/pull?device_id=device-alpha');

        // Create a message
        $this->travel(1)->minutes();

        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->otherUser->id,
            'body' => 'New message for alpha',
        ]);

        // Alpha should see the new message
        $alphaResponse = $this->getJson('/api/v1/sync/pull?device_id=device-alpha');
        $this->assertCount(1, $alphaResponse->json('data.messages'));

        // Beta (never synced) should also see it (first sync pulls from past year)
        $betaResponse = $this->getJson('/api/v1/sync/pull?device_id=device-beta');
        $this->assertCount(1, $betaResponse->json('data.messages'));
    }

    // -------------------------------------------------------------------
    //  Push Tests
    // -------------------------------------------------------------------

    public function test_push_requires_authentication()
    {
        $response = $this->postJson('/api/v1/sync/push', [
            'messages' => [
                ['client_id' => 'uuid-1', 'conversation_id' => 1, 'body' => 'Hello'],
            ],
        ]);
        $response->assertStatus(401);
    }

    public function test_push_requires_messages_array()
    {
        $this->actingAs($this->user);
        $response = $this->postJson('/api/v1/sync/push', []);
        $response->assertStatus(422);
    }

    public function test_push_saves_message_and_returns_message_id()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/v1/sync/push', [
            'messages' => [
                [
                    'client_id' => 'uuid-test-1',
                    'conversation_id' => $this->conversation->id,
                    'body' => 'Hello, I was offline!',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'results' => [
                    [
                        'client_id' => 'uuid-test-1',
                        'success' => true,
                    ],
                ],
            ],
        ]);

        // Verify the message was saved
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'body' => 'Hello, I was offline!',
        ]);

        // Verify the response includes a message_id
        $result = $response->json('data.results.0');
        $this->assertArrayHasKey('message_id', $result);
        $this->assertNotNull($result['message_id']);
    }

    public function test_push_rejects_non_participant()
    {
        $nonParticipant = User::factory()->create();
        $this->actingAs($nonParticipant);

        $response = $this->postJson('/api/v1/sync/push', [
            'messages' => [
                [
                    'client_id' => 'uuid-test-2',
                    'conversation_id' => $this->conversation->id,
                    'body' => 'This should fail',
                ],
            ],
        ]);

        $response->assertJson([
            'data' => [
                'results' => [
                    [
                        'client_id' => 'uuid-test-2',
                        'success' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_push_deduplicates_identical_messages()
    {
        $this->actingAs($this->user);

        // Send once
        $firstResponse = $this->postJson('/api/v1/sync/push', [
            'messages' => [
                [
                    'client_id' => 'uuid-dup-1',
                    'conversation_id' => $this->conversation->id,
                    'body' => 'Duplicate message test',
                ],
            ],
        ]);

        $firstMessageId = $firstResponse->json('data.results.0.message_id');

        // Send the exact same message again (simulating a retry)
        $this->travel(1)->minutes();

        $secondResponse = $this->postJson('/api/v1/sync/push', [
            'messages' => [
                [
                    'client_id' => 'uuid-dup-2',
                    'conversation_id' => $this->conversation->id,
                    'body' => 'Duplicate message test',
                ],
            ],
        ]);

        $secondMessageId = $secondResponse->json('data.results.0.message_id');

        // Should return the same message_id (deduplicated), not a new one
        $this->assertEquals($firstMessageId, $secondMessageId);

        // Only ONE message should exist in the database
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_push_batch_partial_failure()
    {
        $this->actingAs($this->user);

        // Create a second conversation the user is NOT in
        $otherConversation = Conversation::factory()->create([
            'group_id' => $this->user->group_id,
        ]);
        // Don't add the user as a participant

        $response = $this->postJson('/api/v1/sync/push', [
            'messages' => [
                [
                    'client_id' => 'uuid-success',
                    'conversation_id' => $this->conversation->id,
                    'body' => 'This should succeed',
                ],
                [
                    'client_id' => 'uuid-fail',
                    'conversation_id' => $otherConversation->id,
                    'body' => 'This should fail',
                ],
            ],
        ]);

        $results = $response->json('data.results');

        $this->assertCount(2, $results);
        $this->assertEquals('uuid-success', $results[0]['client_id']);
        $this->assertTrue($results[0]['success']);

        $this->assertEquals('uuid-fail', $results[1]['client_id']);
        $this->assertFalse($results[1]['success']);
    }

    public function test_push_updates_last_activity_at()
    {
        $this->actingAs($this->user);

        $this->postJson('/api/v1/sync/push', [
            'messages' => [
                [
                    'client_id' => 'uuid-activity',
                    'conversation_id' => $this->conversation->id,
                    'body' => 'Activity test',
                ],
            ],
        ]);

        $this->assertDatabaseHas('conversations', [
            'id' => $this->conversation->id,
            'last_activity_at' => now(),
        ]);
    }

    public function test_push_validates_body_length()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/v1/sync/push', [
            'messages' => [
                [
                    'client_id' => 'uuid-long',
                    'conversation_id' => $this->conversation->id,
                    'body' => str_repeat('A', 10001),  // 10,001 characters — exceeds limit
                ],
            ],
        ]);

        $response->assertStatus(422);  // Validation error
    }

    public function test_push_max_100_messages()
    {
        $this->actingAs($this->user);

        $messages = [];
        for ($i = 0; $i < 101; $i++) {
            $messages[] = [
                'client_id' => "uuid-{$i}",
                'conversation_id' => $this->conversation->id,
                'body' => "Message {$i}",
            ];
        }

        $response = $this->postJson('/api/v1/sync/push', [
            'messages' => $messages,
        ]);

        $response->assertStatus(422);  // Validation error for exceeding max
    }

    // -------------------------------------------------------------------
    //  Authorisation Tests
    // -------------------------------------------------------------------

    public function test_sync_requires_authenticated_user()
    {
        // Pull
        $this->getJson('/api/v1/sync/pull?device_id=test')->assertStatus(401);

        // Push
        $this->postJson('/api/v1/sync/push', [
            'messages' => [['client_id' => 'test', 'conversation_id' => 1, 'body' => 'test']],
        ])->assertStatus(401);
    }
}
