<?php

namespace Tests\Feature\Web;

use App\Models\ParticipationActivity;
use App\Models\Quiz;
use App\Models\Topic;
use App\Services\ParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class ParticipationTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    // ============================================
    // DAILY LOGIN
    // ============================================

    public function test_daily_login_awards_point_once_per_day(): void
    {
        $user = $this->createStudent();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123',
        ]);

        $this->post('/logout');

        // Second login on the same day must not add another award
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123',
        ]);

        $this->assertSame(1, ParticipationActivity::where('user_id', $user->id)
            ->where('activity_type', ParticipationActivity::TYPE_DAILY_LOGIN)
            ->count());

        $this->assertEquals(
            config('participation.weights.daily_login'),
            app(ParticipationService::class)->totalFor($user)
        );
    }

    // ============================================
    // FORUM ACTIVITY
    // ============================================

    public function test_creating_a_topic_awards_points(): void
    {
        $user = $this->createStudent();

        $response = $this->actingAs($user)->post(route('forum.store'), [
            'title' => 'Participation test topic',
            'description' => 'A topic created to earn participation points.',
        ]);

        $response->assertRedirect(route('forum.index'));

        $topic = Topic::where('title', 'Participation test topic')->first();

        $this->assertDatabaseHas('participation_activities', [
            'user_id' => $user->id,
            'activity_type' => ParticipationActivity::TYPE_TOPIC_CREATED,
            'subject_type' => Topic::class,
            'subject_id' => $topic->id,
            'points' => config('participation.weights.topic_created'),
        ]);
    }

    public function test_replying_to_a_topic_awards_points(): void
    {
        $user = $this->createStudent();

        $topic = Topic::create([
            'title' => 'Existing discussion',
            'description' => 'Original topic body',
            'post_type' => 'discussion',
            'created_by' => $user->id,
            'group_id' => $this->defaultGroup->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('forum.reply.store', $topic), [
            'content' => 'A reply that earns participation points.',
        ]);

        $response->assertRedirect(route('forum.show', $topic->id));

        $this->assertDatabaseHas('participation_activities', [
            'user_id' => $user->id,
            'activity_type' => ParticipationActivity::TYPE_REPLY_POSTED,
            'points' => config('participation.weights.reply_posted'),
        ]);
    }

    // ============================================
    // QUIZ COMPLETION
    // ============================================

    public function test_quiz_completion_awards_points_once_per_quiz(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();

        $quiz = Quiz::create([
            'lecturer_id' => $lecturer->id,
            'group_id' => $this->defaultGroup->id,
            'title' => 'Participation quiz',
            'target_category' => 'Student',
            'scheduled_date' => now()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
        ]);

        $service = app(ParticipationService::class);
        $service->recordQuizCompleted($student, $quiz);

        // Regrading the same quiz must never double-count
        $service->recordQuizCompleted($student, $quiz);

        $this->assertSame(1, ParticipationActivity::where('user_id', $student->id)
            ->where('activity_type', ParticipationActivity::TYPE_QUIZ_COMPLETED)
            ->count());

        $this->assertEquals(
            config('participation.weights.quiz_completed'),
            $service->totalFor($student)
        );
    }

    public function test_quiz_completion_outweighs_other_activities(): void
    {
        $weights = config('participation.weights');

        $this->assertGreaterThan($weights['topic_created'], $weights['quiz_completed']);
        $this->assertGreaterThan($weights['reply_posted'], $weights['topic_created']);
        $this->assertGreaterThan($weights['daily_login'], $weights['reply_posted']);
    }

    // ============================================
    // PROFILE PAGE
    // ============================================

    public function test_profile_page_shows_participation_score(): void
    {
        $user = $this->createStudent();

        app(ParticipationService::class)->recordDailyLogin($user);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertStatus(200);
        $response->assertSee('Participation score');
        $response->assertSee('Active days');
    }

    // ============================================
    // LECTURER / ADMIN OVERVIEW
    // ============================================

    public function test_lecturer_sees_participation_of_students_in_their_groups(): void
    {
        $lecturer = $this->createLecturer();
        $ownStudent = $this->createStudent(['full_name' => 'Own Group Student']);
        $otherStudent = $this->createStudent([
            'full_name' => 'Other Group Student',
            'group_id' => $this->secondGroup->id,
        ]);

        $response = $this->actingAs($lecturer)->get(route('participation.students'));

        $response->assertStatus(200);
        $response->assertSee('Own Group Student');
        $response->assertDontSee('Other Group Student');
    }

    public function test_system_admin_sees_all_students(): void
    {
        $admin = $this->createSystemAdmin();
        $this->createStudent(['full_name' => 'Own Group Student']);
        $this->createStudent([
            'full_name' => 'Other Group Student',
            'group_id' => $this->secondGroup->id,
        ]);

        $response = $this->actingAs($admin)->get(route('participation.students'));

        $response->assertStatus(200);
        $response->assertSee('Own Group Student');
        $response->assertSee('Other Group Student');
    }

    public function test_student_cannot_view_participation_overview(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student)->get(route('participation.students'));

        $response->assertStatus(403);
    }
}
