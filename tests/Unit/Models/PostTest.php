<?php

namespace Tests\Unit\Models;

use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_reported_attribute_casts_to_boolean(): void
    {
        // Test with true value
        $post = Post::factory()->create(['is_reported' => true]);
        $this->assertTrue($post->is_reported);

        // Test with false value
        $post2 = Post::factory()->create(['is_reported' => false]);
        $this->assertFalse($post2->is_reported);

        // Test with integer values (should be cast to boolean)
        $post3 = Post::factory()->create(['is_reported' => 1]);
        $this->assertTrue($post3->is_reported);

        $post4 = Post::factory()->create(['is_reported' => 0]);
        $this->assertFalse($post4->is_reported);
    }

    public function test_is_removed_attribute_casts_to_boolean(): void
    {
        // Test with true value
        $post = Post::factory()->create(['is_removed' => true]);
        $this->assertTrue($post->is_removed);

        // Test with false value
        $post2 = Post::factory()->create(['is_removed' => false]);
        $this->assertFalse($post2->is_removed);
    }

    public function test_reported_scope_filters_reported_posts(): void
    {
        // Create some reported posts
        $reportedPost1 = Post::factory()->create(['is_reported' => true]);
        $reportedPost2 = Post::factory()->create(['is_reported' => true]);

        // Create some non-reported posts
        $notReportedPost1 = Post::factory()->create(['is_reported' => false]);
        $notReportedPost2 = Post::factory()->create(['is_reported' => false]);

        // Use the reported scope
        $reportedPosts = Post::reported()->get();

        // Assert that only reported posts are returned
        $this->assertCount(2, $reportedPosts);
        $this->assertTrue($reportedPosts->contains($reportedPost1));
        $this->assertTrue($reportedPosts->contains($reportedPost2));
        $this->assertFalse($reportedPosts->contains($notReportedPost1));
        $this->assertFalse($reportedPosts->contains($notReportedPost2));
    }

    public function test_removed_scope_filters_removed_posts(): void
    {
        // Create some removed posts
        $removedPost1 = Post::factory()->create(['is_removed' => true]);
        $removedPost2 = Post::factory()->create(['is_removed' => true]);

        // Create some non-removed posts
        $notRemovedPost1 = Post::factory()->create(['is_removed' => false]);
        $notRemovedPost2 = Post::factory()->create(['is_removed' => false]);

        // Use the removed scope
        $removedPosts = Post::removed()->get();

        // Assert that only removed posts are returned
        $this->assertCount(2, $removedPosts);
        $this->assertTrue($removedPosts->contains($removedPost1));
        $this->assertTrue($removedPosts->contains($removedPost2));
        $this->assertFalse($removedPosts->contains($notRemovedPost1));
        $this->assertFalse($removedPosts->contains($notRemovedPost2));
    }

    public function test_not_removed_scope_filters_non_removed_posts(): void
    {
        // Create some removed posts
        $removedPost1 = Post::factory()->create(['is_removed' => true]);
        $removedPost2 = Post::factory()->create(['is_removed' => true]);

        // Create some non-removed posts
        $notRemovedPost1 = Post::factory()->create(['is_removed' => false]);
        $notRemovedPost2 = Post::factory()->create(['is_removed' => false]);

        // Use the notRemoved scope
        $notRemovedPosts = Post::notRemoved()->get();

        // Assert that only non-removed posts are returned
        $this->assertCount(2, $notRemovedPosts);
        $this->assertTrue($notRemovedPosts->contains($notRemovedPost1));
        $this->assertTrue($notRemovedPosts->contains($notRemovedPost2));
        $this->assertFalse($notRemovedPosts->contains($removedPost1));
        $this->assertFalse($notRemovedPosts->contains($removedPost2));
    }

    public function test_moderation_logs_relationship(): void
    {
        $post = Post::factory()->create();
        $admin = User::factory()->create();

        // Create moderation logs for the post
        $moderationLog1 = ModerationLog::factory()->create([
            'post_id' => $post->id,
            'admin_id' => $admin->id
        ]);
        $moderationLog2 = ModerationLog::factory()->create([
            'post_id' => $post->id,
            'admin_id' => $admin->id
        ]);

        // Load the moderation logs relationship
        $post->load('moderationLogs');

        $this->assertCount(2, $post->moderationLogs);
        $this->assertTrue($post->moderationLogs->contains($moderationLog1));
        $this->assertTrue($post->moderationLogs->contains($moderationLog2));
    }

    public function test_topic_relationship(): void
    {
        $topic = Topic::factory()->create();
        $post = Post::factory()->create(['topic_id' => $topic->id]);

        $this->assertInstanceOf(Topic::class, $post->topic);
        $this->assertEquals($topic->id, $post->topic->id);
    }

    public function test_user_relationship(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $post->user);
        $this->assertEquals($user->id, $post->user->id);
    }
}