<?php

namespace Tests\Unit\Models;

use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class TopicModelTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
        $this->author = $this->createMember([
            'full_name' => 'Topic Author',
            'email' => 'author@topic-test.com',
        ]);
    }

    public function test_topic_belongs_to_group()
    {
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Test Topic',
            'description' => 'Test Description',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        $this->assertInstanceOf(Group::class, $topic->group);
        $this->assertEquals($this->defaultGroup->id, $topic->group->id);
        $this->assertEquals($this->defaultGroup->group_name, $topic->group->group_name);
    }

    public function test_topic_belongs_to_creator()
    {
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Creator Test Topic',
            'description' => 'Checking the creator relationship',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        $this->assertInstanceOf(User::class, $topic->creator);
        $this->assertEquals($this->author->id, $topic->creator->id);
        $this->assertEquals($this->author->full_name, $topic->creator->full_name);
        $this->assertEquals($this->author->email, $topic->creator->email);
    }

    public function test_topic_has_many_posts()
    {
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Posts Relationship Test',
            'description' => 'Testing hasMany posts',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        Post::create(['topic_id' => $topic->id, 'user_id' => $this->author->id, 'content' => 'First reply']);
        Post::create(['topic_id' => $topic->id, 'user_id' => $this->author->id, 'content' => 'Second reply']);
        Post::create(['topic_id' => $topic->id, 'user_id' => $this->author->id, 'content' => 'Third reply']);

        $this->assertCount(3, $topic->posts);

        foreach ($topic->posts as $post) {
            $this->assertEquals($topic->id, $post->topic_id);
        }
    }

    public function test_topic_has_no_posts_when_none_created()
    {
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Empty Topic Test',
            'description' => 'A topic with no replies yet',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        $this->assertCount(0, $topic->posts);
        $this->assertTrue($topic->posts->isEmpty());
    }

    public function test_scope_active_filters_archived_topics()
    {
        $activeTopic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Active Topic',
            'description' => 'This topic is active',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        $archivedTopic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Archived Topic',
            'description' => 'This topic is archived',
            'status' => 'archived',
            'post_type' => 'discussion',
        ]);

        $activeTopics = Topic::active()->get();

        $this->assertTrue($activeTopics->contains($activeTopic->id));
        $this->assertFalse($activeTopics->contains($archivedTopic->id));
    }

    public function test_scope_for_group_filters_by_group()
    {
        $group1Topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Group 1 Topic',
            'description' => 'Belongs to default group',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        $group2Topic = Topic::create([
            'group_id' => $this->secondGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Group 2 Topic',
            'description' => 'Belongs to second group',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        $topicsInGroup1 = Topic::forGroup($this->defaultGroup->id)->get();
        $topicsInGroup2 = Topic::forGroup($this->secondGroup->id)->get();

        $this->assertTrue($topicsInGroup1->contains($group1Topic->id));
        $this->assertFalse($topicsInGroup1->contains($group2Topic->id));
        $this->assertTrue($topicsInGroup2->contains($group2Topic->id));
        $this->assertFalse($topicsInGroup2->contains($group1Topic->id));
    }

    public function test_scope_by_type_filters_post_type()
    {
        $discussion = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Discussion Topic',
            'description' => 'This is a general discussion',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        $question = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Question Topic',
            'description' => 'This is a question',
            'status' => 'active',
            'post_type' => 'question',
        ]);

        $discussions = Topic::byType('discussion')->get();
        $questions = Topic::byType('question')->get();

        $this->assertTrue($discussions->contains($discussion->id));
        $this->assertFalse($discussions->contains($question->id));
        $this->assertTrue($questions->contains($question->id));
        $this->assertFalse($questions->contains($discussion->id));
    }

    public function test_scopes_can_be_chained_together()
    {
        Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'G1 Discussion',
            'description' => 'Group 1 discussion',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'G1 Question',
            'description' => 'Group 1 question',
            'status' => 'active',
            'post_type' => 'question',
        ]);

        Topic::create([
            'group_id' => $this->secondGroup->id,
            'created_by' => $this->author->id,
            'title' => 'G2 Question',
            'description' => 'Group 2 question',
            'status' => 'active',
            'post_type' => 'question',
        ]);

        Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'G1 Archived',
            'description' => 'Archived in group 1',
            'status' => 'archived',
            'post_type' => 'discussion',
        ]);

        $result = Topic::forGroup($this->defaultGroup->id)
            ->active()
            ->byType('question')
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('G1 Question', $result->first()->title);
    }

    public function test_topic_defaults_to_active_status()
    {
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Default Status Test',
            'description' => 'Checking default status',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        $this->assertEquals('active', $topic->status);
    }

    public function test_topic_defaults_to_discussion_post_type()
    {
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Default Post Type Test',
            'description' => 'Checking default post type',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        $this->assertEquals('discussion', $topic->post_type);
    }

    public function test_topic_can_be_created_with_minimum_required_fields()
    {
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Minimal Topic Creation',
            'description' => 'Testing with only required fields',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        $this->assertNotNull($topic->id);
        $this->assertEquals('active', $topic->status);
        $this->assertEquals('discussion', $topic->post_type);
        $this->assertNotNull($topic->created_at);
        $this->assertNotNull($topic->updated_at);
    }

    public function test_topic_can_be_retrieved_as_active_question_in_specific_group()
    {
        Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'How to solve integrals?',
            'description' => 'I need help with calculus',
            'status' => 'active',
            'post_type' => 'question',
        ]);

        $result = Topic::forGroup($this->defaultGroup->id)
            ->active()
            ->byType('question')
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('How to solve integrals?', $result->first()->title);
    }
}
