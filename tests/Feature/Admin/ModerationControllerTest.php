<?php

namespace Tests\Feature\Admin;

use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class ModerationControllerTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    public function test_index_returns_view_with_reported_posts_for_system_admin(): void
    {
        // Create system admin user
        $admin = $this->createSystemAdmin();
        $this->actingAs($admin);

        // Create some groups
        $group1 = Group::factory()->create();
        $group2 = Group::factory()->create();

        // Create topics in different groups
        $topic1 = Topic::factory()->create(['group_id' => $group1->id]);
        $topic2 = Topic::factory()->create(['group_id' => $group2->id]);

        // Create user for posts
        $user = $this->createStudent();

        // Create reported posts
        $post1 = Post::factory()->create([
            'topic_id' => $topic1->id,
            'user_id' => $user->id,
            'is_reported' => true,
        ]);
        $post2 = Post::factory()->create([
            'topic_id' => $topic2->id,
            'user_id' => $user->id,
            'is_reported' => true,
        ]);

        // Make request to moderation index
        $response = $this->get(route('admin.moderation.index'));

        // Assert response
        $response->assertStatus(200);
        $response->assertViewIs('admin.moderation-index');
        $response->assertViewHas('reportedPosts', function ($reportedPosts) use ($post1, $post2) {
            return $reportedPosts->count() === 2 &&
                   $reportedPosts->contains($post1) &&
                   $reportedPosts->contains($post2);
        });
    }

    public function test_index_returns_only_posts_from_administrable_groups_for_group_admin(): void
    {
        // Create group admin user
        $admin = $this->createGroupAdmin();
        $this->actingAs($admin);

        // Create two groups - one admin can manage, one they can't
        $group1 = Group::factory()->create();
        $group2 = Group::factory()->create();

        // Assign admin to manage only group1
        $group1->addAdmin($admin);

        // Create topics in both groups
        $topic1 = Topic::factory()->create(['group_id' => $group1->id]);
        $topic2 = Topic::factory()->create(['group_id' => $group2->id]);

        // Create user for posts
        $user = $this->createStudent();

        // Create reported posts in both groups
        $postInManagedGroup = Post::factory()->create([
            'topic_id' => $topic1->id,
            'user_id' => $user->id,
            'is_reported' => true,
        ]);
        $postInUnmanagedGroup = Post::factory()->create([
            'topic_id' => $topic2->id,
            'user_id' => $user->id,
            'is_reported' => true,
        ]);

        // Make request to moderation index
        $response = $this->get(route('admin.moderation.index'));

        // Assert response contains only posts from managed group
        $response->assertStatus(200);
        $response->assertViewHas('reportedPosts', function ($reportedPosts) use ($postInManagedGroup, $postInUnmanagedGroup) {
            return $reportedPosts->count() === 1 &&
                   $reportedPosts->contains($postInManagedGroup) &&
                   ! $reportedPosts->contains($postInUnmanagedGroup);
        });
    }

    public function test_remove_post_removes_post_and_creates_moderation_log(): void
    {
        // Create system admin user
        $admin = $this->createSystemAdmin();
        $this->actingAs($admin);

        // Create group, topic, and user
        $group = Group::factory()->create();
        $topic = Topic::factory()->create(['group_id' => $group->id]);
        $user = $this->createStudent();

        // Create reported post
        $post = Post::factory()->create([
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'is_reported' => true,
        ]);

        // Submit remove post request
        $response = $this->post(route('admin.moderation.remove', $post), [
            'reason' => 'Inappropriate content',
        ]);

        // Assert redirection back with success message
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Post removed.');

        // Refresh post and assert it's marked as removed and unflagged
        $post->refresh();
        $this->assertTrue($post->is_removed);
        $this->assertFalse($post->is_reported);

        // Assert moderation log was created
        $this->assertDatabaseHas('moderation_logs', [
            'post_id' => $post->id,
            'admin_id' => $admin->id,
            'action' => 'removed',
            'reason' => 'Inappropriate content',
        ]);
    }

    public function test_remove_post_fails_for_unauthorized_user(): void
    {
        // Create regular student user (not admin)
        $user = $this->createStudent();
        $this->actingAs($user);

        // Create group, topic, and another user
        $group = Group::factory()->create();
        $topic = Topic::factory()->create(['group_id' => $group->id]);
        $anotherUser = $this->createStudent();

        // Create reported post
        $post = Post::factory()->create([
            'topic_id' => $topic->id,
            'user_id' => $anotherUser->id,
            'is_reported' => true,
        ]);

        // Try to remove post (should fail with 403)
        $response = $this->post(route('admin.moderation.remove', $post));

        $response->assertForbidden();
    }

    public function test_ignore_report_unflags_post_without_removal(): void
    {
        // Create system admin user
        $admin = $this->createSystemAdmin();
        $this->actingAs($admin);

        // Create group, topic, and user
        $group = Group::factory()->create();
        $topic = Topic::factory()->create(['group_id' => $group->id]);
        $user = $this->createStudent();

        // Create reported post
        $post = Post::factory()->create([
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'is_reported' => true,
        ]);

        // Submit ignore report request
        $response = $this->post(route('admin.moderation.ignore', $post));

        // Assert redirection back with success message
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Report dismissed.');

        // Refresh post and assert it's no longer flagged but not removed
        $post->refresh();
        $this->assertFalse($post->is_reported);
        $this->assertFalse($post->is_removed);

        // Assert no moderation log was created for ignore action
        $this->assertDatabaseCount('moderation_logs', 0);
    }

    public function test_ignore_report_fails_for_unauthorized_user(): void
    {
        // Create regular student user (not admin)
        $user = $this->createStudent();
        $this->actingAs($user);

        // Create group, topic, and another user
        $group = Group::factory()->create();
        $topic = Topic::factory()->create(['group_id' => $group->id]);
        $anotherUser = $this->createStudent();

        // Create reported post
        $post = Post::factory()->create([
            'topic_id' => $topic->id,
            'user_id' => $anotherUser->id,
            'is_reported' => true,
        ]);

        // Try to ignore report (should fail with 403)
        $response = $this->post(route('admin.moderation.ignore', $post));

        $response->assertForbidden();
    }

    public function test_remove_post_works_for_group_admin_on_managed_group(): void
    {
        // Create group admin user
        $admin = $this->createGroupAdmin();
        $this->actingAs($admin);

        // Create group and assign admin to manage it
        $group = Group::factory()->create();
        $group->addAdmin($admin);

        // Create topic and user
        $topic = Topic::factory()->create(['group_id' => $group->id]);
        $user = $this->createStudent();

        // Create reported post
        $post = Post::factory()->create([
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'is_reported' => true,
        ]);

        // Submit remove post request
        $response = $this->post(route('admin.moderation.remove', $post), [
            'reason' => 'Violates group rules',
        ]);

        // Should succeed
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Post removed.');

        // Refresh post and assert it's marked as removed
        $post->refresh();
        $this->assertTrue($post->is_removed);
        $this->assertFalse($post->is_reported);
    }

    public function test_remove_post_fails_for_group_admin_on_unmanaged_group(): void
    {
        // Create group admin user
        $admin = $this->createGroupAdmin();
        $this->actingAs($admin);

        // Create group but don't assign admin to manage it
        $group = Group::factory()->create();

        // Create topic and user
        $topic = Topic::factory()->create(['group_id' => $group->id]);
        $user = $this->createStudent();

        // Create reported post
        $post = Post::factory()->create([
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'is_reported' => true,
        ]);

        // Try to remove post (should fail with 403)
        $response = $this->post(route('admin.moderation.remove', $post), [
            'reason' => 'Should not work',
        ]);

        $response->assertForbidden();
    }
}
