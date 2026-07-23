<?php

namespace Tests\Unit\Models;

use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $post = Post::factory()->create();
        $admin = User::factory()->create();

        $attributes = [
            'post_id' => $post->id,
            'admin_id' => $admin->id,
            'action' => 'removed',
            'reason' => 'Violates community guidelines',
        ];

        $moderationLog = ModerationLog::create($attributes);

        $this->assertEquals($attributes['post_id'], $moderationLog->post_id);
        $this->assertEquals($attributes['admin_id'], $moderationLog->admin_id);
        $this->assertEquals($attributes['action'], $moderationLog->action);
        $this->assertEquals($attributes['reason'], $moderationLog->reason);
    }

    public function test_post_relationship(): void
    {
        $post = Post::factory()->create();
        $admin = User::factory()->create();

        $moderationLog = ModerationLog::factory()->create([
            'post_id' => $post->id,
            'admin_id' => $admin->id,
        ]);

        $this->assertInstanceOf(Post::class, $moderationLog->post);
        $this->assertEquals($post->id, $moderationLog->post->id);
    }

    public function test_admin_relationship(): void
    {
        $post = Post::factory()->create();
        $admin = User::factory()->create();

        $moderationLog = ModerationLog::factory()->create([
            'post_id' => $post->id,
            'admin_id' => $admin->id,
        ]);

        $this->assertInstanceOf(User::class, $moderationLog->admin);
        $this->assertEquals($admin->id, $moderationLog->admin->id);
    }

    public function test_reason_field_can_be_nullable(): void
    {
        $post = Post::factory()->create();
        $admin = User::factory()->create();

        $moderationLog = ModerationLog::create([
            'post_id' => $post->id,
            'admin_id' => $admin->id,
            'action' => 'removed',
            'reason' => null,
        ]);

        $this->assertNull($moderationLog->reason);
    }

    public function test_timestamps_are_automatically_set(): void
    {
        $post = Post::factory()->create();
        $admin = User::factory()->create();

        $moderationLog = ModerationLog::create([
            'post_id' => $post->id,
            'admin_id' => $admin->id,
            'action' => 'removed',
            'reason' => 'Test reason',
        ]);

        $this->assertNotNull($moderationLog->created_at);
        $this->assertNotNull($moderationLog->updated_at);
        $this->assertInstanceOf(Carbon::class, $moderationLog->created_at);
        $this->assertInstanceOf(Carbon::class, $moderationLog->updated_at);
    }
}
