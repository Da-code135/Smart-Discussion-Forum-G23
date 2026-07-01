<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use Tests\CreatesTestUsers;
use App\Models\Post;
use App\Models\User;
use App\Models\Topic;
use App\Models\PostVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PostVisibilityTest extends TestCase
{
    use RefreshDatabase, CreatesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    public function test_visible_to_user_scope_excludes_posts_where_user_is_blocked()
    {
        // Create users
        $author = $this->createMember(['full_name' => 'Author', 'email' => 'author@example.com']);
        $excludedUser = $this->createMember(['full_name' => 'Excluded', 'email' => 'excluded@example.com']);
        $normalUser = $this->createMember(['full_name' => 'Normal', 'email' => 'normal@example.com']);
        
        // Create a topic
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $author->id,
            'title' => 'Test Topic',
            'description' => 'Test Description',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        // Create a post
        $post = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $author->id,
            'content' => 'This is a test post',
        ]);

        // Create a visibility exclusion for the excluded user
        PostVisibility::create([
            'post_id' => $post->id,
            'excluded_user_id' => $excludedUser->id,
        ]);

        // Test that the excluded user cannot see the post
        $postsVisibleToExcludedUser = Post::visibleToUser($excludedUser->id)->get();
        $this->assertFalse($postsVisibleToExcludedUser->contains($post->id));

        // Test that the normal user can see the post
        $postsVisibleToNormalUser = Post::visibleToUser($normalUser->id)->get();
        $this->assertTrue($postsVisibleToNormalUser->contains($post->id));
    }

    public function test_not_removed_scope_filters_out_removed_posts()
    {
        // Create users
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

        // Create a normal post
        $normalPost = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $author->id,
            'content' => 'This is a normal post',
            'is_removed' => false,
        ]);

        // Create a removed post
        $removedPost = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $author->id,
            'content' => 'This is a removed post',
            'is_removed' => true,
        ]);

        // Test that notRemoved scope excludes removed posts
        $postsAfterNotRemovedScope = Post::notRemoved()->get();
        $this->assertTrue($postsAfterNotRemovedScope->contains($normalPost->id));
        $this->assertFalse($postsAfterNotRemovedScope->contains($removedPost->id));
    }

    public function test_post_has_visibility_exclusions_relationship()
    {
        // Create users
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

        // Create a post
        $post = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $author->id,
            'content' => 'This is a test post',
        ]);

        // Create a visibility exclusion
        $visibilityExclusion = PostVisibility::create([
            'post_id' => $post->id,
            'excluded_user_id' => $excludedUser->id,
        ]);

        // Reload the post to ensure relationships are fresh
        $post->refresh();

        // Test the relationship
        $this->assertCount(1, $post->visibilityExclusions);
        $this->assertEquals($visibilityExclusion->id, $post->visibilityExclusions->first()->id);
        $this->assertEquals($excludedUser->id, $post->visibilityExclusions->first()->excluded_user_id);
    }
}