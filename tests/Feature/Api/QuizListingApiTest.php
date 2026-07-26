<?php

namespace Tests\Feature\Api;

use App\Models\Quiz;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesTestUsers;
use Tests\TestCase;

/**
 * Covers the quiz listing endpoints consumed by the desktop client:
 *   GET /api/v1/quizzes          (lecturer/admin management list)
 *   GET /api/v1/quizzes/upcoming (student cards)
 *   GET /api/v1/quizzes/live     (student cards)
 *   GET /api/v1/me/quiz-history  (student cards)
 *
 * These tests guard alignment with the web quiz index filtering:
 * lecturers see only their own quizzes, students see only published
 * quizzes targeted at their role in their group (or general quizzes).
 */
class QuizListingApiTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    public function test_lecturer_quiz_index_shows_only_own_quizzes(): void
    {
        $lecturer = $this->createLecturer();
        $otherLecturer = $this->createLecturer();
        Sanctum::actingAs($lecturer);

        $ownQuiz = $this->makeQuiz($lecturer, ['title' => 'My Quiz']);
        $this->makeQuiz($otherLecturer, ['title' => 'Colleague Quiz']);

        $response = $this->getJson('/api/v1/quizzes');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.quizzes.data')
            ->assertJsonPath('data.quizzes.data.0.quiz_id', $ownQuiz->quiz_id)
            ->assertJsonPath('data.quizzes.data.0.lecturer.full_name', $lecturer->full_name);
    }

    public function test_system_admin_quiz_index_shows_all_quizzes(): void
    {
        $lecturer = $this->createLecturer();
        $admin = $this->createSystemAdmin();
        Sanctum::actingAs($admin);

        $this->makeQuiz($lecturer);
        $this->makeQuiz($lecturer, ['title' => 'Second Quiz']);

        $response = $this->getJson('/api/v1/quizzes');

        $response->assertOk()->assertJsonCount(2, 'data.quizzes.data');
    }

    public function test_upcoming_quizzes_are_filtered_by_role_and_include_general_quizzes(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();
        Sanctum::actingAs($student);

        // Visible: published Student quiz in the student's group
        $groupQuiz = $this->makeQuiz($lecturer, ['published_at' => now()]);
        // Visible: published general quiz with no group
        $generalQuiz = $this->makeQuiz($lecturer, [
            'title' => 'General Quiz',
            'group_id' => null,
            'published_at' => now(),
        ]);
        // Hidden: targeted at lecturers
        $this->makeQuiz($lecturer, [
            'title' => 'Lecturer Quiz',
            'target_category' => 'Lecturer',
            'published_at' => now(),
        ]);
        // Hidden: draft (never published)
        $this->makeQuiz($lecturer, ['title' => 'Draft Quiz']);
        // Hidden: other group
        $this->makeQuiz($lecturer, [
            'title' => 'Other Group Quiz',
            'group_id' => $this->secondGroup->id,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/quizzes/upcoming');

        $response->assertOk()->assertJsonCount(2, 'data.quizzes');

        $ids = collect($response->json('data.quizzes'))->pluck('quiz_id');
        $this->assertTrue($ids->contains($groupQuiz->quiz_id));
        $this->assertTrue($ids->contains($generalQuiz->quiz_id));
    }

    public function test_live_quizzes_are_filtered_by_role_and_published_state(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();
        Sanctum::actingAs($student);

        $liveQuiz = $this->makeQuiz($lecturer, [
            'is_active' => true,
            'published_at' => now(),
        ]);
        // Hidden: active but never published
        $this->makeQuiz($lecturer, ['title' => 'Unpublished Live', 'is_active' => true]);
        // Hidden: live quiz targeted at lecturers
        $this->makeQuiz($lecturer, [
            'title' => 'Lecturer Live',
            'target_category' => 'Lecturer',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/quizzes/live');

        $response->assertOk()
            ->assertJsonCount(1, 'data.quizzes')
            ->assertJsonPath('data.quizzes.0.quiz_id', $liveQuiz->quiz_id);
    }

    public function test_quiz_history_returns_attempts_with_quiz_title_and_grade(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();
        Sanctum::actingAs($student);

        $quiz = $this->makeQuiz($lecturer, ['published_at' => now()]);
        StudentAttempt::create([
            'quiz_id' => $quiz->quiz_id,
            'student_id' => $student->id,
            'start_time' => now()->subMinutes(30),
            'submit_time' => now()->subMinutes(10),
            'is_auto_submit' => false,
            'is_late' => false,
        ]);

        $response = $this->getJson('/api/v1/me/quiz-history');

        $response->assertOk()
            ->assertJsonCount(1, 'data.attempts')
            ->assertJsonPath('data.attempts.0.quiz_id', $quiz->quiz_id)
            ->assertJsonPath('data.attempts.0.quiz_title', $quiz->title);
    }

    private function makeQuiz(User $lecturer, array $attrs = []): Quiz
    {
        return Quiz::create(array_merge([
            'lecturer_id' => $lecturer->id,
            'group_id' => $this->defaultGroup->id,
            'title' => 'Fixture quiz',
            'target_category' => 'Student',
            'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
            'is_active' => false,
            'published_at' => null,
        ], $attrs));
    }
}
