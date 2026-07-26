<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\TopicCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

/**
 * Tests for keyword-based topic classification with confidence scoring
 * and low-confidence review flagging (SDD §5.2.2 / Appendix A).
 *
 * Topics are classified automatically on creation via the Topic::created
 * model event, so these tests create topics and inspect the refreshed row.
 */
class TopicClassificationTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    /**
     * Create a topic with fully controlled title/description so faker text
     * can't introduce accidental keyword matches.
     */
    private function makeTopic(User $creator, string $title, string $description): Topic
    {
        return Topic::factory()->create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $creator->id,
            'title' => $title,
            'description' => $description,
        ]);
    }

    public function test_strong_keyword_match_yields_full_confidence_without_review_flag(): void
    {
        $student = $this->createStudent();

        $topic = $this->makeTopic($student, 'Django views and models', 'Learning the python framework.');
        $topic->refresh();

        $this->assertSame('Django', $topic->category->category_name);
        $this->assertSame(100, $topic->classification_confidence);
        $this->assertFalse($topic->classification_needs_review);
    }

    public function test_ambiguous_topic_is_flagged_for_admin_review(): void
    {
        $student = $this->createStudent();

        // One keyword each for Django, APIs and CSS: winner gets 1 of 3
        // matches = 33% confidence, below the default 40% threshold.
        $topic = $this->makeTopic($student, 'django api css', 'Which one should I pick?');
        $topic->refresh();

        $this->assertSame(33, $topic->classification_confidence);
        $this->assertTrue($topic->classification_needs_review);
    }

    public function test_unmatched_topic_falls_back_to_general_and_is_flagged(): void
    {
        $student = $this->createStudent();

        $topic = $this->makeTopic($student, 'Hello everyone welcome', 'Nothing relevant to review.');
        $topic->refresh();

        $this->assertSame('General', $topic->category->category_name);
        $this->assertSame(0, $topic->classification_confidence);
        $this->assertTrue($topic->classification_needs_review);
    }

    public function test_admin_keyword_hints_extend_the_classifier(): void
    {
        $student = $this->createStudent();

        // Admin-defined category with its own keywords (SDD Appendix A:
        // "Admins can add new keywords over time").
        TopicCategory::create([
            'group_id' => $this->defaultGroup->id,
            'category_name' => 'Mathematics',
            'keyword_hints' => 'algebra, calculus',
        ]);

        $topic = $this->makeTopic($student, 'Solving algebra and calculus', 'Practice with worked examples.');
        $topic->refresh();

        $this->assertSame('Mathematics', $topic->category->category_name);
        $this->assertSame(100, $topic->classification_confidence);
        $this->assertFalse($topic->classification_needs_review);
    }

    public function test_flagged_topics_are_queryable_via_review_scope(): void
    {
        $student = $this->createStudent();

        $flagged = $this->makeTopic($student, 'Hello everyone welcome', 'Nothing relevant to review.');
        $confident = $this->makeTopic($student, 'Django views and models', 'Learning the python framework.');

        $flaggedIds = Topic::needsClassificationReview()->pluck('id');

        $this->assertTrue($flaggedIds->contains($flagged->id));
        $this->assertFalse($flaggedIds->contains($confident->id));
    }
}
