<?php

namespace Tests\Feature\Web;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class ForumAnsweredFilterTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    private function createTopic(User $creator, array $attrs = []): Topic
    {
        return Topic::create(array_merge([
            'title' => 'Topic '.uniqid(),
            'description' => 'A topic description.',
            'post_type' => 'discussion',
            'created_by' => $creator->id,
            'group_id' => $this->defaultGroup->id,
            'status' => 'active',
        ], $attrs));
    }

    public function test_questions_filter_shows_only_question_topics(): void
    {
        $student = $this->createStudent();
        $discussion = $this->createTopic($student, ['title' => 'General discussion topic']);
        $question = $this->createTopic($student, [
            'title' => 'How do migrations work?',
            'post_type' => 'question',
        ]);

        $response = $this->actingAs($student)->get(route('forum.index', ['filter' => 'questions']));

        $response->assertOk();
        $response->assertSee($question->title);
        $response->assertDontSee($discussion->title);
    }

    public function test_unanswered_filter_excludes_answered_questions(): void
    {
        $student = $this->createStudent();
        $answered = $this->createTopic($student, [
            'title' => 'Answered question topic',
            'post_type' => 'question',
            'is_answered' => true,
        ]);
        $unanswered = $this->createTopic($student, [
            'title' => 'Unanswered question topic',
            'post_type' => 'question',
        ]);

        $response = $this->actingAs($student)->get(route('forum.index', ['filter' => 'unanswered']));

        $response->assertOk();
        $response->assertSee($unanswered->title);
        $response->assertDontSee($answered->title);
    }

    public function test_mine_filter_shows_only_own_topics(): void
    {
        $asker = $this->createStudent(['full_name' => 'Asker Student']);
        $other = $this->createStudent(['full_name' => 'Other Student']);
        $mine = $this->createTopic($asker, ['title' => 'My own question here', 'post_type' => 'question']);
        $theirs = $this->createTopic($other, ['title' => 'Someone elses topic here']);

        $response = $this->actingAs($asker)->get(route('forum.index', ['filter' => 'mine']));

        $response->assertOk();
        $response->assertSee($mine->title);
        $response->assertDontSee($theirs->title);
    }

    public function test_answered_badge_shown_on_forum_index(): void
    {
        $student = $this->createStudent();
        $this->createTopic($student, [
            'title' => 'Answered badge question',
            'post_type' => 'question',
            'is_answered' => true,
        ]);

        $response = $this->actingAs($student)->get(route('forum.index'));

        $response->assertOk();
        $response->assertSee('✓ Answered');
    }

    public function test_creator_can_toggle_answered_status_via_web(): void
    {
        $asker = $this->createStudent();
        $topic = $this->createTopic($asker, ['post_type' => 'question']);

        $response = $this->actingAs($asker)->post(route('forum.toggle-answered', $topic->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue($topic->fresh()->is_answered);

        // Toggle back to unanswered
        $this->actingAs($asker)->post(route('forum.toggle-answered', $topic->id));
        $this->assertFalse($topic->fresh()->is_answered);
    }

    public function test_non_creator_cannot_toggle_answered_status(): void
    {
        $asker = $this->createStudent();
        $other = $this->createStudent(['full_name' => 'Other Student']);
        $topic = $this->createTopic($asker, ['post_type' => 'question']);

        $response = $this->actingAs($other)->post(route('forum.toggle-answered', $topic->id));

        $response->assertForbidden();
        $this->assertFalse($topic->fresh()->is_answered);
    }

    public function test_discussion_topics_cannot_be_marked_answered(): void
    {
        $creator = $this->createStudent();
        $topic = $this->createTopic($creator);

        $response = $this->actingAs($creator)->post(route('forum.toggle-answered', $topic->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertFalse($topic->fresh()->is_answered);
    }

    public function test_api_unanswered_filter_returns_only_unanswered_questions(): void
    {
        $student = $this->createStudent();
        $this->createTopic($student, [
            'title' => 'API answered question',
            'post_type' => 'question',
            'is_answered' => true,
        ]);
        $unanswered = $this->createTopic($student, [
            'title' => 'API unanswered question',
            'post_type' => 'question',
        ]);
        $this->createTopic($student, ['title' => 'API plain discussion']);

        Sanctum::actingAs($student);
        $response = $this->getJson('/api/v1/topics?filter=unanswered');

        $response->assertOk();
        $titles = collect($response->json('data.data'))->pluck('title');
        $this->assertTrue($titles->contains($unanswered->title));
        $this->assertCount(1, $titles);
    }
}
