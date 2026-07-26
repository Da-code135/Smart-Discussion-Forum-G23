<?php

namespace Tests\Feature\Api;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesTestUsers;
use Tests\TestCase;

/**
 * Covers the lecturer quiz management API flow used by the desktop client:
 * create quiz → add question (with inline answers) → reorder questions.
 *
 * The reorder test also guards the route ordering in routes/api.php —
 * "/quizzes/{quiz}/questions/reorder" must be registered before the
 * parameterized "/quizzes/{quiz}/questions/{question}" routes.
 */
class QuizManagementApiTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    private function actingAsLecturer(): User
    {
        $lecturer = $this->createLecturer();
        Sanctum::actingAs($lecturer);

        return $lecturer;
    }

    public function test_lecturer_can_create_quiz(): void
    {
        $this->actingAsLecturer();

        $response = $this->postJson('/api/v1/quizzes', [
            'title' => 'Week 5 Quiz',
            'description' => 'Database normalization',
            'target_category' => 'Student',
            'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quiz.title', 'Week 5 Quiz');

        $this->assertDatabaseHas('quizzes', [
            'title' => 'Week 5 Quiz',
            'group_id' => $this->defaultGroup->id,
        ]);
    }

    public function test_quiz_creation_fails_with_invalid_payload(): void
    {
        $this->actingAsLecturer();

        // Legacy desktop payload shape — must be rejected, not silently accepted
        $response = $this->postJson('/api/v1/quizzes', [
            'title' => 'Bad Quiz',
            'type' => 'Quiz',
            'passing_score' => 50,
            'start_at' => '2026-08-01 08:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_category', 'scheduled_date', 'start_time', 'duration_minutes']);
    }

    public function test_lecturer_can_add_question_with_answers(): void
    {
        $lecturer = $this->actingAsLecturer();

        $quiz = $this->makeQuiz($lecturer);

        $response = $this->postJson("/api/v1/quizzes/{$quiz->quiz_id}/questions", [
            'question_text' => 'Is 2NF stricter than 1NF?',
            'question_type' => 'TF',
            'marks' => 5,
            'answers' => [
                ['answer_text' => 'True', 'is_correct' => true],
                ['answer_text' => 'False', 'is_correct' => false],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.question.question_type', 'TF');

        $this->assertDatabaseCount('answers', 2);
    }

    public function test_lecturer_can_reorder_questions(): void
    {
        $lecturer = $this->actingAsLecturer();

        $quiz = $this->makeQuiz($lecturer);
        $first = $this->makeQuestion($quiz->quiz_id, 1);
        $second = $this->makeQuestion($quiz->quiz_id, 2);

        $response = $this->putJson("/api/v1/quizzes/{$quiz->quiz_id}/questions/reorder", [
            'questions' => [
                ['id' => $first->question_id, 'order' => 2],
                ['id' => $second->question_id, 'order' => 1],
            ],
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $this->assertSame(2, $first->fresh()->question_order);
        $this->assertSame(1, $second->fresh()->question_order);
    }

    private function makeQuiz(User $lecturer): Quiz
    {
        return Quiz::create([
            'lecturer_id' => $lecturer->id,
            'group_id' => $this->defaultGroup->id,
            'title' => 'Fixture quiz',
            'target_category' => 'Student',
            'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
        ]);
    }

    private function makeQuestion(int $quizId, int $order): Question
    {
        return Question::create([
            'quiz_id' => $quizId,
            'question_text' => "Question {$order}",
            'question_type' => 'MCQ',
            'marks' => 5,
            'question_order' => $order,
        ]);
    }
}
