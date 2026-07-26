<?php

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\Topic;
use App\Models\TopicCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class DashboardRecommendationTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    /**
     * Create an active topic in the default group.
     */
    private function makeTopic(User $creator, string $title, ?int $categoryId = null): Topic
    {
        return Topic::factory()->create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $creator->id,
            'title' => $title,
            'category_id' => $categoryId,
        ]);
    }

    public function test_student_sees_recommendation_card_on_dashboard(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();

        $this->makeTopic($lecturer, 'Popular study thread');

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Recommended')
            ->assertSee('Popular study thread');
    }

    public function test_lecturer_sees_recommendation_card_on_dashboard(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();

        $this->makeTopic($student, 'Question about assignment two');

        $response = $this->actingAs($lecturer)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Recommended')
            ->assertSee('Question about assignment two');
    }

    public function test_engaged_student_still_gets_recommendations_when_category_matches_run_dry(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();

        $category = TopicCategory::create([
            'group_id' => $this->defaultGroup->id,
            'category_name' => 'Databases',
        ]);

        // Student has posted in the ONLY topic of this category, so the
        // category-based recommendation query returns nothing.
        $engagedTopic = $this->makeTopic($lecturer, 'Normalization basics', $category->id);
        Post::factory()->create([
            'topic_id' => $engagedTopic->id,
            'user_id' => $student->id,
        ]);

        // A popular topic elsewhere should top up the recommendations.
        $this->makeTopic($lecturer, 'Exam preparation tips');

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Recommended')
            ->assertSee('Exam preparation tips');
    }

    public function test_recommendations_page_shows_relevance_score_and_reason(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();

        // Both topics auto-classify into the Django category via keywords.
        $engagedTopic = Topic::factory()->create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $lecturer->id,
            'title' => 'Django models question',
            'description' => 'Getting started together.',
        ]);
        $suggestedTopic = Topic::factory()->create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $lecturer->id,
            'title' => 'Django views help',
            'description' => 'Setting up class based screens.',
        ]);

        // All of the student's engagement is in Django => 100% relevance.
        Post::factory()->create([
            'topic_id' => $engagedTopic->id,
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($student)->get(route('recommendations.index'));

        $response->assertOk()
            ->assertSee('Django views help')
            ->assertSee('100% match')
            ->assertSee('Based on similar topics you engaged with');

        $this->assertDatabaseHas('recommendation_log', [
            'user_id' => $student->id,
            'topic_id' => $suggestedTopic->id,
            'relevance_score' => 100,
        ]);
    }

    public function test_popular_fallback_recommendations_carry_reason_and_score(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();

        // Student has never posted, so popular fallback kicks in.
        $this->makeTopic($lecturer, 'Welcome thread for everyone');

        $response = $this->actingAs($student)->get(route('recommendations.index'));

        $response->assertOk()
            ->assertSee('Welcome thread for everyone')
            ->assertSee('% match')
            ->assertSee('Popular in your group');
    }
}
