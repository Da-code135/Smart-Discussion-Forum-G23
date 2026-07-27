<?php

namespace Tests\Feature\Web;

use App\Models\Notification;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class QuestionReplyNotificationTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    /**
     * Create a question topic owned by the given user in the default group.
     */
    private function createQuestion(User $asker, string $title = 'How does X work?'): Topic
    {
        return Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $asker->id,
            'title' => $title,
            'description' => 'Question description',
            'status' => 'active',
            'post_type' => 'question',
        ]);
    }

    public function test_web_reply_to_question_notifies_asker_with_title_and_message(): void
    {
        $asker = $this->createStudent(['full_name' => 'Alice Asker']);
        $replier = $this->createStudent(['full_name' => 'Bob Replier']);
        $topic = $this->createQuestion($asker);

        $this->actingAs($replier)
            ->post(route('forum.reply.store', $topic->id), ['content' => 'Here is the answer.'])
            ->assertRedirect(route('forum.show', $topic->id));

        $notification = Notification::where('user_id', $asker->id)
            ->where('type', 'question_answered')
            ->first();

        $this->assertNotNull($notification, 'Asker should receive a question_answered notification.');
        $this->assertSame('New answer to your question', $notification->title);
        $this->assertStringContainsString('Bob Replier', $notification->message);
        $this->assertStringContainsString($topic->title, $notification->message);
        $this->assertSame($asker->group_id, $notification->group_id);
        $this->assertSame($topic->id, $notification->data['topic_id']);
        $this->assertNotNull($notification->data['post_id']);
    }

    public function test_web_reply_auto_marks_question_as_answered(): void
    {
        $asker = $this->createStudent();
        $replier = $this->createStudent();
        $topic = $this->createQuestion($asker);

        $this->actingAs($replier)
            ->post(route('forum.reply.store', $topic->id), ['content' => 'Answer.']);

        $this->assertTrue($topic->fresh()->is_answered);
    }

    public function test_reply_to_own_question_creates_no_notification(): void
    {
        $asker = $this->createStudent();
        $topic = $this->createQuestion($asker);

        $this->actingAs($asker)
            ->post(route('forum.reply.store', $topic->id), ['content' => 'Answering myself.']);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $asker->id,
            'type' => 'question_answered',
        ]);
    }

    public function test_reply_to_discussion_topic_creates_no_notification(): void
    {
        $author = $this->createStudent();
        $replier = $this->createStudent();

        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $author->id,
            'title' => 'General discussion',
            'description' => 'Discussion description',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        $this->actingAs($replier)
            ->post(route('forum.reply.store', $topic->id), ['content' => 'A discussion reply.']);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $author->id,
            'type' => 'question_answered',
        ]);
        $this->assertFalse($topic->fresh()->is_answered);
    }

    public function test_api_reply_to_question_notifies_asker_with_title_and_message(): void
    {
        $asker = $this->createStudent(['full_name' => 'Carol Asker']);
        $replier = $this->createStudent(['full_name' => 'Dave Replier']);
        $topic = $this->createQuestion($asker, 'API question?');

        Sanctum::actingAs($replier);

        $this->postJson("/api/v1/topics/{$topic->id}/posts", ['content' => 'Answer via API.'])
            ->assertStatus(201);

        $notification = Notification::where('user_id', $asker->id)
            ->where('type', 'question_answered')
            ->first();

        $this->assertNotNull($notification, 'Asker should be notified for API replies too.');
        $this->assertSame('New answer to your question', $notification->title);
        $this->assertStringContainsString('Dave Replier', $notification->message);
        $this->assertSame($topic->id, $notification->data['topic_id']);
        $this->assertTrue($topic->fresh()->is_answered);
    }
}
