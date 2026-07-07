<?php

namespace Tests\Feature\View;

use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class ModerationViewTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    public function test_moderation_view_displays_reported_posts(): void
    {
        // Create system admin user
        $admin = $this->createSystemAdmin();
        $this->actingAs($admin);

        // Create group, topic, and user
        $group = Group::factory()->create();
        $topic = Topic::factory()->create(['group_id' => $group->id]);
        $user = $this->createStudent();

        // Create reported posts
        $post1 = Post::factory()->create([
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'is_reported' => true,
            'content' => 'This is a reported post content',
        ]);
        $post2 = Post::factory()->create([
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'is_reported' => true,
            'content' => 'Another reported post content',
        ]);

        // Request the moderation page
        $response = $this->get(route('admin.moderation.index'));

        // Assert the view renders correctly and contains the reported posts
        $response->assertStatus(200);
        $response->assertViewIs('admin.moderation-index');

        // Check that the reported posts are passed to the view
        $response->assertViewHas('reportedPosts', function ($reportedPosts) use ($post1, $post2) {
            return $reportedPosts->count() >= 2 &&
                   $reportedPosts->contains($post1) &&
                   $reportedPosts->contains($post2);
        });

        // Check that the view contains content from the posts
        $response->assertSee($post1->user->full_name);
        $response->assertSee($post2->user->full_name);
        $response->assertSee($topic->title);
        $response->assertSee(e(Str::limit($post1->content, 200)));
        $response->assertSee(e(Str::limit($post2->content, 200)));
    }

    public function test_moderation_view_shows_empty_state_when_no_reported_posts(): void
    {
        // Create system admin user
        $admin = $this->createSystemAdmin();
        $this->actingAs($admin);

        // Ensure no reported posts exist
        Post::where('is_reported', true)->delete();

        // Request the moderation page
        $response = $this->get(route('admin.moderation.index'));

        // Assert the view renders correctly and shows empty state
        $response->assertStatus(200);
        $response->assertViewIs('admin.moderation-index');

        // Check that an empty collection is passed
        $response->assertViewHas('reportedPosts', function ($reportedPosts) {
            return $reportedPosts->count() === 0;
        });

        // Check that the "No reported posts" message appears
        $response->assertSee('No reported posts.');
    }

    public function test_moderation_view_contains_required_action_buttons(): void
    {
        // Create system admin user
        $admin = $this->createSystemAdmin();
        $this->actingAs($admin);

        // Create group, topic, and user
        $group = Group::factory()->create();
        $topic = Topic::factory()->create(['group_id' => $group->id]);
        $user = $this->createStudent();

        // Create a reported post
        $post = Post::factory()->create([
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'is_reported' => true,
        ]);

        // Request the moderation page
        $response = $this->get(route('admin.moderation.index'));

        // Assert the response contains the required action buttons and forms
        $response->assertStatus(200);

        // Check that the remove post form exists with correct action
        $response->assertSee(route('admin.moderation.remove', $post), false);

        // Check that the ignore report form exists with correct action
        $response->assertSee(route('admin.moderation.ignore', $post), false);

        // Check that the required button texts are present
        $response->assertSee('Remove Post');
        $response->assertSee('Ignore Report');
    }

    public function test_moderation_view_requires_authentication(): void
    {
        // Make request without authentication
        $response = $this->get(route('admin.moderation.index'));

        // Should redirect to login
        $response->assertRedirect(route('login'));
    }

    public function test_moderation_view_requires_admin_privileges(): void
    {
        // Create regular user (not admin)
        $user = $this->createStudent();
        $this->actingAs($user);

        // Request the moderation page
        $response = $this->get(route('admin.moderation.index'));

        // Should be forbidden for non-admins
        $response->assertForbidden();
    }
}
