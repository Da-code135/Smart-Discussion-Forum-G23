<?php

namespace Tests\Feature\Console;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlagPostCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_flag_command_flags_existing_post(): void
    {
        // Create a post
        $post = Post::factory()->create(['is_reported' => false]);

        // Execute the command
        $this->artisan('posts:flag', ['post' => $post->id])
            ->assertExitCode(0);

        // Assert the post is flagged in the database
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'is_reported' => true,
        ]);
    }

    public function test_flag_command_shows_error_for_nonexistent_post(): void
    {
        // Execute the command with non-existent post ID
        $result = $this->artisan('posts:flag', ['post' => 999]);

        // Assert the command failed
        $result->assertExitCode(1);

        // Assert error message
        $result->expectsOutput('Post not found.');
    }

    public function test_flag_command_handles_already_flagged_post(): void
    {
        // Create an already flagged post
        $post = Post::factory()->create(['is_reported' => true]);

        // Execute the command
        $result = $this->artisan('posts:flag', ['post' => $post->id]);

        // Assert the command succeeded (but doesn't double-flag)
        $result->assertExitCode(0);

        // Assert warning message
        $result->expectsOutput("Post #{$post->id} is already flagged.");

        // Refresh the post and assert it remains flagged
        $post->refresh();
        $this->assertTrue($post->is_reported);
    }

    public function test_flag_command_outputs_success_message(): void
    {
        // Create a post
        $post = Post::factory()->create(['is_reported' => false]);

        // Execute the command
        $result = $this->artisan('posts:flag', ['post' => $post->id]);

        // Assert success message
        $result->expectsOutput("Post #{$post->id} flagged for moderation.");
    }
}
