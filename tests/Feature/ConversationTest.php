<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    // ============================================
    // INDEX — User isolation
    // ============================================

    public function test_user_sees_only_their_own_conversations(): void
    {
        $userA = $this->createStudent();
        $userB = $this->createStudent();

        // User A creates a conversation
        $conversation = Conversation::create([
            'group_id' => $this->defaultGroup->id,
            'type' => 'direct',
            'last_activity_at' => now(),
        ]);
        $conversation->participants()->attach([
            $userA->id => ['role' => 'participant', 'joined_at' => now()],
            $userB->id => ['role' => 'participant', 'joined_at' => now()],
        ]);

        // User C should not see it
        $userC = $this->createStudent();
        $response = $this->actingAs($userC)->getJson('/api/v1/conversations');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.data'));
    }

    // ============================================
    // STORE — Direct conversation reuse
    // ============================================

    public function test_starting_direct_conversation_with_same_partner_reuses_existing(): void
    {
        $userA = $this->createStudent();
        $userB = $this->createStudent(['group_id' => $this->defaultGroup->id]);

        // First creation
        $response1 = $this->actingAs($userA)->postJson('/api/v1/conversations', [
            'type' => 'direct',
            'participant_ids' => [$userB->id],
        ]);
        $response1->assertStatus(201);
        $conversationId = $response1->json('data.id');

        // Second creation — should reuse
        $response2 = $this->actingAs($userA)->postJson('/api/v1/conversations', [
            'type' => 'direct',
            'participant_ids' => [$userB->id],
        ]);
        $response2->assertStatus(200);
        $this->assertEquals($conversationId, $response2->json('data.id'));
    }

    // ============================================
    // STORE — Cross-group block
    // ============================================

    public function test_cross_group_direct_conversation_returns_422(): void
    {
        $userA = $this->createStudent(); // defaultGroup
        $userB = $this->createStudent(['group_id' => $this->secondGroup->id]);

        $response = $this->actingAs($userA)->postJson('/api/v1/conversations', [
            'type' => 'direct',
            'participant_ids' => [$userB->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => "User {$userB->full_name} is not in your group. Conversations are limited to group members only."]);
    }

    // ============================================
    // STORE — Group conversation creator is admin
    // ============================================

    public function test_group_conversation_creator_is_admin(): void
    {
        $userA = $this->createStudent();
        $userB = $this->createStudent(['group_id' => $this->defaultGroup->id]);

        $response = $this->actingAs($userA)->postJson('/api/v1/conversations', [
            'type' => 'group',
            'name' => 'Test Group',
            'participant_ids' => [$userB->id],
        ]);

        $response->assertStatus(201);
        $conversationId = $response->json('data.id');

        $conversation = Conversation::find($conversationId);
        $creatorPivot = $conversation->participants()->where('user_id', $userA->id)->first()->pivot;
        $this->assertEquals('admin', $creatorPivot->role);

        $otherPivot = $conversation->participants()->where('user_id', $userB->id)->first()->pivot;
        $this->assertEquals('participant', $otherPivot->role);
    }

    // ============================================
    // ADD PARTICIPANT — Non-admin gets 403
    // ============================================

    public function test_non_admin_participant_cannot_add_members(): void
    {
        $userA = $this->createStudent(); // creator
        $userB = $this->createStudent(['group_id' => $this->defaultGroup->id]); // non-admin participant
        $userC = $this->createStudent(['group_id' => $this->defaultGroup->id]); // target

        $conversation = Conversation::create([
            'group_id' => $this->defaultGroup->id,
            'type' => 'group',
            'name' => 'Test Group',
            'last_activity_at' => now(),
        ]);
        $conversation->participants()->attach($userA->id, ['role' => 'admin', 'joined_at' => now()]);
        $conversation->participants()->attach($userB->id, ['role' => 'participant', 'joined_at' => now()]);

        $response = $this->actingAs($userB)->postJson("/api/v1/conversations/{$conversation->id}/participants", [
            'user_id' => $userC->id,
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Only conversation admins can manage participants.']);
    }

    // ============================================
    // ADD/REMOVE — Admin can manage
    // ============================================

    public function test_admin_can_add_and_remove_participants(): void
    {
        $userA = $this->createStudent(); // creator/admin
        $userB = $this->createStudent(['group_id' => $this->defaultGroup->id]); // target to add
        $userC = $this->createStudent(['group_id' => $this->defaultGroup->id]); // target to add then remove

        $conversation = Conversation::create([
            'group_id' => $this->defaultGroup->id,
            'type' => 'group',
            'name' => 'Test Group',
            'last_activity_at' => now(),
        ]);
        $conversation->participants()->attach($userA->id, ['role' => 'admin', 'joined_at' => now()]);
        $conversation->participants()->attach($userB->id, ['role' => 'participant', 'joined_at' => now()]);

        // Add userC
        $addResponse = $this->actingAs($userA)->postJson("/api/v1/conversations/{$conversation->id}/participants", [
            'user_id' => $userC->id,
        ]);
        $addResponse->assertStatus(200);
        $this->assertTrue(
            $conversation->participants()->where('user_id', $userC->id)->exists()
        );

        // Remove userC
        $removeResponse = $this->actingAs($userA)->deleteJson("/api/v1/conversations/{$conversation->id}/participants/{$userC->id}");
        $removeResponse->assertStatus(200);
        $this->assertFalse(
            $conversation->participants()->where('user_id', $userC->id)->exists()
        );
    }

    // ============================================
    // ADD PARTICIPANT — Cross-group block
    // ============================================

    public function test_adding_cross_group_user_to_conversation_returns_422(): void
    {
        $userA = $this->createStudent(); // defaultGroup
        $userB = $this->createStudent(['group_id' => $this->defaultGroup->id]);
        $crossGroupUser = $this->createStudent(['group_id' => $this->secondGroup->id]);

        $conversation = Conversation::create([
            'group_id' => $this->defaultGroup->id,
            'type' => 'group',
            'name' => 'Test Group',
            'last_activity_at' => now(),
        ]);
        $conversation->participants()->attach($userA->id, ['role' => 'admin', 'joined_at' => now()]);
        $conversation->participants()->attach($userB->id, ['role' => 'participant', 'joined_at' => now()]);

        $response = $this->actingAs($userA)->postJson("/api/v1/conversations/{$conversation->id}/participants", [
            'user_id' => $crossGroupUser->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Cannot add a user from a different group to this conversation.']);
    }

    // ============================================
    // ORDER — Sorted by last_activity_at desc
    // ============================================

    public function test_conversation_list_ordered_by_last_activity_at_desc(): void
    {
        $user = $this->createStudent();
        $otherUser = $this->createStudent(['group_id' => $this->defaultGroup->id]);

        $older = Conversation::create([
            'group_id' => $this->defaultGroup->id,
            'type' => 'direct',
            'last_activity_at' => now()->subDay(),
        ]);
        $older->participants()->attach([
            $user->id => ['role' => 'participant', 'joined_at' => now()->subDay()],
            $otherUser->id => ['role' => 'participant', 'joined_at' => now()->subDay()],
        ]);

        $newer = Conversation::create([
            'group_id' => $this->defaultGroup->id,
            'type' => 'direct',
            'last_activity_at' => now(),
        ]);
        $newer->participants()->attach([
            $user->id => ['role' => 'participant', 'joined_at' => now()],
            $otherUser->id => ['role' => 'participant', 'joined_at' => now()],
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/conversations');
        $response->assertStatus(200);

        $ids = collect($response->json('data.data'))->pluck('id')->toArray();
        $this->assertEquals([$newer->id, $older->id], $ids);
    }

    // ============================================
    // SHOW — Non-participant gets 404
    // ============================================

    public function test_non_participant_cannot_view_conversation(): void
    {
        $userA = $this->createStudent();
        $userB = $this->createStudent();

        $conversation = Conversation::create([
            'group_id' => $this->defaultGroup->id,
            'type' => 'direct',
            'last_activity_at' => now(),
        ]);
        $conversation->participants()->attach($userA->id, ['role' => 'participant', 'joined_at' => now()]);

        $userC = $this->createStudent(); // not a participant
        $response = $this->actingAs($userC)->getJson("/api/v1/conversations/{$conversation->id}");
        $response->assertStatus(404);
    }

    // ============================================
    // REMOVE — Self-remove returns 422
    // ============================================

    public function test_admin_cannot_remove_self_from_group_conversation(): void
    {
        $userA = $this->createStudent();
        $userB = $this->createStudent(['group_id' => $this->defaultGroup->id]);

        $conversation = Conversation::create([
            'group_id' => $this->defaultGroup->id,
            'type' => 'group',
            'name' => 'Test Group',
            'last_activity_at' => now(),
        ]);
        $conversation->participants()->attach($userA->id, ['role' => 'admin', 'joined_at' => now()]);
        $conversation->participants()->attach($userB->id, ['role' => 'participant', 'joined_at' => now()]);

        $response = $this->actingAs($userA)->deleteJson("/api/v1/conversations/{$conversation->id}/participants/{$userA->id}");
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'You cannot remove yourself from the conversation.']);
    }

    // ============================================
    // ADD — Direct conversation cannot have participants added
    // ============================================

    public function test_cannot_add_participants_to_direct_conversation(): void
    {
        $userA = $this->createStudent();
        $userB = $this->createStudent(['group_id' => $this->defaultGroup->id]);
        $userC = $this->createStudent(['group_id' => $this->defaultGroup->id]);

        $conversation = Conversation::create([
            'group_id' => $this->defaultGroup->id,
            'type' => 'direct',
            'last_activity_at' => now(),
        ]);
        $conversation->participants()->attach([
            $userA->id => ['role' => 'participant', 'joined_at' => now()],
            $userB->id => ['role' => 'participant', 'joined_at' => now()],
        ]);

        $response = $this->actingAs($userA)->postJson("/api/v1/conversations/{$conversation->id}/participants", [
            'user_id' => $userC->id,
        ]);
        $response->assertStatus(422);
    }
}
