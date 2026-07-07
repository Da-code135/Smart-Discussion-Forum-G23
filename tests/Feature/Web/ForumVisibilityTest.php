<?php

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\PostVisibility;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class ForumVisibilityTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    public function test_post_author_can_exclude_user_from_seeing_their_post()
    {
        // Create users in the same group
        $author = $this->createMember(['full_name' => 'Author', 'email' => 'author@example.com']);
        $excludedUser = $this->createMember(['full_name' => 'Excluded', 'email' => 'excluded@example.com']);
        $otherUser = $this->createMember(['full_name' => 'Other', 'email' => 'other@example.com']);

        // Create a topic
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $author->id,
            'title' => 'Test Topic',
            'description' => 'Test Description',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        // Create a post by the author
        $post = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $author->id,
            'content' => 'This is a test post',
        ]);

        // Authenticate as the post author
        $this->actingAs($author);

        // Submit request to exclude the user
        $response = $this->post(route('forum.visibility.exclude', $post->id), [
            'user_id' => $excludedUser->id,
        ]);

        // Assert the response redirects back
        $response->assertRedirect();

        // Assert that the PostVisibility record was created
        $this->assertDatabaseHas('post_visibility', [
            'post_id' => $post->id,
            'excluded_user_id' => $excludedUser->id,
        ]);

        // Verify that the excluded user cannot see the post
        $this->actingAs($excludedUser);
        $topic->load(['posts' => function ($query) {
            $query->notRemoved()
                ->visibleToUser(Auth::id())
                ->orderBy('created_at', 'asc')
                ->with('user');
        }]);

        // The excluded user should not see the post
        $postVisibleToExcludedUser = $topic->posts->contains($post->id);
        $this->assertFalse($postVisibleToExcludedUser, 'Excluded user should not see the post');

        // Verify that other users can still see the post
        $this->actingAs($otherUser);
        $topic->load(['posts' => function ($query) {
            $query->notRemoved()
                ->visibleToUser(Auth::id())
                ->orderBy('created_at', 'asc')
                ->with('user');
        }]);

        // Other users should still see the post
        $postVisibleToOtherUser = $topic->posts->contains($post->id);
        $this->assertTrue($postVisibleToOtherUser, 'Other users should still see the post');
    }

    public function test_non_author_cannot_exclude_users_from_post()
    {
        // Create users in the same group
        $author = $this->createMember(['full_name' => 'Author', 'email' => 'author@example.com']);
        $otherUser = $this->createMember(['full_name' => 'Other', 'email' => 'other@example.com']);
        $toBeExcludedUser = $this->createMember(['full_name' => 'ToBeExcluded', 'email' => 'tobeexcluded@example.com']);

        // Create a topic
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $author->id,
            'title' => 'Test Topic',
            'description' => 'Test Description',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        // Create a post by the author
        $post = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $author->id,
            'content' => 'This is a test post',
        ]);

        // Authenticate as a non-author user
        $this->actingAs($otherUser);

        // Try to submit request to exclude a user
        $response = $this->post(route('forum.visibility.exclude', $post->id), [
            'user_id' => $toBeExcludedUser->id,
        ]);

        // Assert that access is forbidden
        $response->assertForbidden();

        // Assert that no PostVisibility record was created
        $this->assertDatabaseMissing('post_visibility', [
            'post_id' => $post->id,
            'excluded_user_id' => $toBeExcludedUser->id,
        ]);
    }

    public function test_validation_fails_with_invalid_user_id()
    {
        // Create users in the same group
        $author = $this->createMember(['full_name' => 'Author', 'email' => 'author@example.com']);

        // Create a topic
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $author->id,
            'title' => 'Test Topic',
            'description' => 'Test Description',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        // Create a post by the author
        $post = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $author->id,
            'content' => 'This is a test post',
        ]);

        // Authenticate as the post author
        $this->actingAs($author);

        // Submit request with invalid user ID
        $response = $this->post(route('forum.visibility.exclude', $post->id), [
            'user_id' => 99999, // Non-existent user ID
        ]);

        // Assert validation error - this may redirect with error instead of showing session errors
        $response->assertRedirect();
        $response->assertSessionHasErrors('user_id');
    }

    public function test_duplicate_exclusion_not_created()
    {
        // Create users in the same group
        $author = $this->createMember(['full_name' => 'Author', 'email' => 'author@example.com']);
        $excludedUser = $this->createMember(['full_name' => 'Excluded', 'email' => 'excluded@example.com']);

        // Create a topic
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $author->id,
            'title' => 'Test Topic',
            'description' => 'Test Description',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        // Create a post by the author
        $post = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $author->id,
            'content' => 'This is a test post',
        ]);

        // Authenticate as the post author
        $this->actingAs($author);

        // First request to exclude the user
        $response1 = $this->post(route('forum.visibility.exclude', $post->id), [
            'user_id' => $excludedUser->id,
        ]);

        // Second request to exclude the same user
        $response2 = $this->post(route('forum.visibility.exclude', $post->id), [
            'user_id' => $excludedUser->id,
        ]);

        // Both requests should succeed but only one record should be created
        $response1->assertRedirect();
        $response2->assertRedirect();

        // Check that only one PostVisibility record exists
        $this->assertEquals(1, PostVisibility::where('post_id', $post->id)
            ->where('excluded_user_id', $excludedUser->id)
            ->count());
    }
}
