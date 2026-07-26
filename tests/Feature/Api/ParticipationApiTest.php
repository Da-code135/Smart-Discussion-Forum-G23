<?php

namespace Tests\Feature\Api;

use App\Models\ParticipationActivity;
use App\Models\Topic;
use App\Services\ParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class ParticipationApiTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    /**
     * Authenticate a user against the API and return the auth header.
     *
     * @return array<string, string>
     */
    protected function authHeaders($user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('test-token')->plainTextToken,
        ];
    }

    public function test_api_login_awards_daily_login_point(): void
    {
        $user = $this->createStudent();

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'Password123',
        ]);

        $response->assertStatus(200);

        $this->assertSame(1, ParticipationActivity::where('user_id', $user->id)
            ->where('activity_type', ParticipationActivity::TYPE_DAILY_LOGIN)
            ->count());
    }

    public function test_creating_topic_via_api_awards_points(): void
    {
        $user = $this->createStudent();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/topics', [
                'title' => 'API participation topic',
                'description' => 'A topic created through the API.',
            ]);

        $response->assertStatus(201);

        $topic = Topic::where('title', 'API participation topic')->first();

        $this->assertDatabaseHas('participation_activities', [
            'user_id' => $user->id,
            'activity_type' => ParticipationActivity::TYPE_TOPIC_CREATED,
            'subject_type' => Topic::class,
            'subject_id' => $topic->id,
            'points' => config('participation.weights.topic_created'),
        ]);
    }

    public function test_replying_via_api_awards_points(): void
    {
        $user = $this->createStudent();

        $topic = Topic::create([
            'title' => 'Existing API discussion',
            'description' => 'Original topic body',
            'post_type' => 'discussion',
            'created_by' => $user->id,
            'group_id' => $this->defaultGroup->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/v1/topics/{$topic->id}/posts", [
                'content' => 'An API reply that earns participation points.',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('participation_activities', [
            'user_id' => $user->id,
            'activity_type' => ParticipationActivity::TYPE_REPLY_POSTED,
            'points' => config('participation.weights.reply_posted'),
        ]);
    }

    public function test_me_endpoint_includes_participation_score(): void
    {
        $user = $this->createStudent();

        app(ParticipationService::class)->recordDailyLogin($user);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'full_name', 'email'],
                'participation' => ['total', 'breakdown'],
            ]);

        $this->assertEquals(
            config('participation.weights.daily_login'),
            $response->json('participation.total')
        );
    }
}
