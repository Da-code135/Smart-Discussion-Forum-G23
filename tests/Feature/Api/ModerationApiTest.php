<?php

namespace Tests\Feature\Api;

use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class ModerationApiTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    public function test_moderation_index_returns_reported_posts_with_reporter_and_creator(): void
    {
        $admin = $this->createSystemAdmin();
        Sanctum::actingAs($admin);

        $group = Group::factory()->create();
        $topic = Topic::factory()->create(['group_id' => $group->id]);
        $author = $this->createStudent();
        $reporter = $this->createStudent();

        $post = Post::factory()->create([
            'topic_id' => $topic->id,
            'user_id' => $author->id,
            'is_reported' => true,
        ]);

        $post->reports()->create([
            'user_id' => $reporter->id,
            'reason' => 'Inappropriate content',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/admin/moderation');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.posts.data.0.id', $post->id)
            ->assertJsonPath('data.posts.data.0.creator.full_name', $author->full_name)
            ->assertJsonPath('data.posts.data.0.topic.title', $topic->title)
            ->assertJsonPath('data.posts.data.0.reports.0.reporter.full_name', $reporter->full_name);
    }

    public function test_moderation_index_is_group_scoped_for_group_admin(): void
    {
        $admin = $this->createGroupAdmin();
        Sanctum::actingAs($admin);

        $managedGroup = Group::factory()->create();
        $managedGroup->addAdmin($admin);
        $otherGroup = Group::factory()->create();

        $author = $this->createStudent();

        $visiblePost = Post::factory()->create([
            'topic_id' => Topic::factory()->create(['group_id' => $managedGroup->id])->id,
            'user_id' => $author->id,
            'is_reported' => true,
        ]);
        Post::factory()->create([
            'topic_id' => Topic::factory()->create(['group_id' => $otherGroup->id])->id,
            'user_id' => $author->id,
            'is_reported' => true,
        ]);

        $response = $this->getJson('/api/v1/admin/moderation');

        $response->assertOk()
            ->assertJsonCount(1, 'data.posts.data')
            ->assertJsonPath('data.posts.data.0.id', $visiblePost->id);
    }
}
