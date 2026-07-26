<?php

namespace Tests\Feature\Web;

use App\Models\Quiz;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class QuizTimingAndReportTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();

        // Freeze time at midday so schedule arithmetic never crosses midnight
        $this->travelTo(Carbon::parse('2026-07-25 12:00:00'));
    }

    /**
     * Create a quiz scheduled today at the given start time.
     */
    private function makeQuiz(User $lecturer, string $startTime, int $durationMinutes = 40): Quiz
    {
        return Quiz::create([
            'lecturer_id' => $lecturer->id,
            'group_id' => $this->defaultGroup->id,
            'title' => 'Timing quiz',
            'target_category' => 'Student',
            'scheduled_date' => now()->toDateString(),
            'start_time' => $startTime,
            'duration_minutes' => $durationMinutes,
            'is_active' => true,
        ]);
    }

    private function makeAttempt(Quiz $quiz, User $student, Carbon $startTime): StudentAttempt
    {
        return StudentAttempt::create([
            'quiz_id' => $quiz->quiz_id,
            'student_id' => $student->id,
            'start_time' => $startTime,
            'is_late' => $startTime->greaterThan($quiz->getScheduledDateTime()),
            'submit_time' => null,
            'is_auto_submit' => false,
        ]);
    }

    public function test_late_joiner_time_is_clipped_at_scheduled_end(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();

        // Scheduled 11:30, 40 min duration -> hard close at 12:10.
        // Student joins at 12:00, so only 600 seconds remain (not 2400).
        $quiz = $this->makeQuiz($lecturer, '11:30', 40);
        $this->makeAttempt($quiz, $student, now());

        $response = $this->actingAs($student)
            ->getJson(route('quizzes.status', $quiz->quiz_id));

        $response->assertOk()
            ->assertJson([
                'has_started' => true,
                'is_submitted' => false,
                'time_remaining' => 600,
            ]);
    }

    public function test_on_time_student_gets_full_duration(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();

        $quiz = $this->makeQuiz($lecturer, '12:00', 40);
        $this->makeAttempt($quiz, $student, now());

        $response = $this->actingAs($student)
            ->getJson(route('quizzes.status', $quiz->quiz_id));

        $response->assertOk()->assertJson(['time_remaining' => 2400]);
    }

    public function test_expired_attempt_reports_zero_time_and_flags_auto_submit(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();

        // Quiz ran 10:00-10:30; the attempt started on time but was never submitted
        $quiz = $this->makeQuiz($lecturer, '10:00', 30);
        $this->makeAttempt($quiz, $student, Carbon::parse('2026-07-25 10:00:00'));

        $response = $this->actingAs($student)
            ->getJson(route('quizzes.status', $quiz->quiz_id));

        $response->assertOk()
            ->assertJson([
                'time_remaining' => 0,
                'auto_submit_if_expired' => true,
            ]);
    }

    public function test_api_status_clips_time_for_late_joiner(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();

        $quiz = $this->makeQuiz($lecturer, '11:30', 40);
        $this->makeAttempt($quiz, $student, now());

        $response = $this->getJson('/api/v1/quizzes/'.$quiz->quiz_id.'/status', [
            'Authorization' => 'Bearer '.$student->createToken('test-token')->plainTextToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.time_remaining', 600);
    }

    public function test_group_member_can_view_report_after_quiz_ends(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();

        // Quiz closed at 10:30 — report is open to group members
        $quiz = $this->makeQuiz($lecturer, '10:00', 30);

        $response = $this->actingAs($student)
            ->get(route('quizzes.report', $quiz->quiz_id));

        $response->assertOk()->assertSee('Performance Report');
    }

    public function test_group_member_cannot_view_report_before_quiz_ends(): void
    {
        $lecturer = $this->createLecturer();
        $student = $this->createStudent();

        // Quiz still in progress (11:50-12:30)
        $quiz = $this->makeQuiz($lecturer, '11:50', 40);

        $this->actingAs($student)
            ->get(route('quizzes.report', $quiz->quiz_id))
            ->assertForbidden();
    }

    public function test_member_of_another_group_cannot_view_report(): void
    {
        $lecturer = $this->createLecturer();
        $outsider = $this->createStudent(['group_id' => $this->secondGroup->id]);

        $quiz = $this->makeQuiz($lecturer, '10:00', 30);

        $this->actingAs($outsider)
            ->get(route('quizzes.report', $quiz->quiz_id))
            ->assertForbidden();
    }

    public function test_lecturer_can_view_report_before_quiz_ends(): void
    {
        $lecturer = $this->createLecturer();

        $quiz = $this->makeQuiz($lecturer, '11:50', 40);

        $this->actingAs($lecturer)
            ->get(route('quizzes.report', $quiz->quiz_id))
            ->assertOk()
            ->assertSee('Performance Report');
    }
}
