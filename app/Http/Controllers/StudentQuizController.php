<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\StudentAttempt;
use App\Models\StudentAnswer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentQuizController extends Controller
{
    /**
     * ============================================
     * SHOW QUIZ ANNOUNCEMENT (Pre-quiz landing page)
     * ============================================
     *
     * This is the page students see BEFORE the quiz starts.
     * It displays:
     *   - Quiz title, description, date/time, duration
     *   - Number of questions
     *   - A live countdown if the quiz starts within 15 minutes
     *   - A "Join Quiz" button once the quiz is live
     *
     * Security:
     *   - Only users whose role matches target_category can see it
     *   - The quiz must have been published (announcement sent)
     */
    public function showAnnouncement(Quiz $quiz)
    {
        $user = Auth::user();

        // Only users matching the target role can access
        if ($quiz->target_category !== $user->role->role_name) {
            abort(403, 'This quiz is not for your role.');
        }

        // Quiz must have been published by the lecturer
        if (!$quiz->published_at) {
            abort(404, 'Quiz announcement not published yet.');
        }

        // Parse the scheduled start time
        $scheduledTime = Carbon::parse(
            $quiz->scheduled_date . ' ' . $quiz->start_time,
        );
        $now = now();

        // Diff in seconds (negative if the start time has passed)
        $timeUntilStart = $scheduledTime->diffInSeconds($now, false);

        $quizStatus = [
            'scheduled_time' => $scheduledTime,
            'time_until_start_seconds' => $timeUntilStart,
            'has_started' => $timeUntilStart <= 0,
            'time_until_start_display' => self::formatTimeRemaining(
                $timeUntilStart,
            ),
        ];

        return view('quizzes.announcement', compact('quiz', 'quizStatus'));
    }

    /**
     * ============================================
     * SHOW ACTIVE QUIZ INTERFACE (The exam screen)
     * ============================================
     *
     * This is the locked quiz-taking screen. It:
     *   1. Verifies the quiz is live (is_active = true)
     *   2. Creates or retrieves the student's attempt record
     *   3. Loads all questions with their answer options
     *   4. Loads any answers the student has already saved
     *   5. Calculates remaining time
     *   6. Auto-submits if time has already expired
     *
     * Security:
     *   - Role matching enforced
     *   - Quiz must be active
     *   - Late-join detection against quiz config
     */
    public function showQuiz(Request $request, Quiz $quiz)
    {
        $user = Auth::user();

        // Role gate
        if ($quiz->target_category !== $user->role->role_name) {
            abort(403, 'This quiz is not for your role.');
        }

        $scheduledTime = Carbon::parse(
            $quiz->scheduled_date . ' ' . $quiz->start_time,
        );
        $now = now();

        // === Quiz not yet active ===
        if (!$quiz->is_active) {
            if ($now->isBefore($scheduledTime)) {
                return redirect()
                    ->route('quizzes.announcement', $quiz->quiz_id)
                    ->with('error', 'Quiz has not started yet.');
            }

            return redirect()
                ->route('quizzes.announcement', $quiz->quiz_id)
                ->with('error', 'Quiz time has passed.');
        }

        // === Late-join check ===
        $isLate = $now->isAfter($scheduledTime);
        if ($isLate && !$quiz->configuration?->allow_late_join) {
            return redirect()
                ->route('quizzes.announcement', $quiz->quiz_id)
                ->with('error', 'Late joining is not allowed for this quiz.');
        }

        // === Find or create the attempt ===
        $existingAttempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->first();

        if ($existingAttempt) {
            // Already started — resume
            $attempt = $existingAttempt;
        } else {
            // First time entering — create a fresh attempt
            $attempt = StudentAttempt::create([
                'quiz_id' => $quiz->quiz_id,
                'student_id' => $user->id,
                'start_time' => $now,
                'is_late' => $isLate,
                'submit_time' => null,
                'is_auto_submit' => false,
            ]);
        }

        // === If already submitted, redirect to results ===
        if ($attempt->submit_time) {
            return redirect()->route('quizzes.result', $quiz->quiz_id);
        }

        // === Load questions + existing answers ===
        $questions = $quiz
            ->questions()
            ->with('answers')
            ->orderBy('question_order')
            ->get();

        $studentAnswers = StudentAnswer::where(
            'attempt_id',
            $attempt->attempt_id,
        )
            ->pluck('selected_answer_id', 'question_id')
            ->toArray();

        // === Calculate remaining time ===
        $timeLimit = $quiz->duration_minutes * 60; // seconds
        $timeElapsed = $now->diffInSeconds($attempt->start_time);
        $timeRemaining = $timeLimit - $timeElapsed;

        if ($timeRemaining <= 0) {
            return $this->autoSubmit($quiz);
        }

        return view('quizzes.attempt', compact(
            'quiz',
            'attempt',
            'questions',
            'studentAnswers',
            'timeRemaining',
            'scheduledTime',
            'isLate',
        ));
    }

    /**
     * ============================================
     * SAVE A SINGLE ANSWER (AJAX endpoint)
     * ============================================
     *
     * Called by JavaScript every time the student selects/deselects an
     * answer option. Answers persist immediately — not just on submit.
     * This prevents data loss if the browser crashes mid-quiz.
     *
     * POST /quizzes/{quiz}/answer
     * Expects JSON: { question_id, answer_id }
     * answer_id may be null (deselection / skip).
     *
     * Security:
     *   - Only the student who owns the attempt can save
     *   - Cannot save after submission
     */
    public function saveAnswer(Request $request, Quiz $quiz)
    {
        $user = Auth::user();

        $attempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->firstOrFail();

        // Cannot change answers after submitting
        if ($attempt->submit_time) {
            return response()->json(
                ['error' => 'Quiz already submitted.'],
                400,
            );
        }

        $validated = $request->validate([
            'question_id' => 'required|exists:questions,question_id',
            'answer_id' => 'nullable|exists:answers,answer_id',
        ]);

        // Remove any previous answer for this question
        StudentAnswer::where('attempt_id', $attempt->attempt_id)
            ->where('question_id', $validated['question_id'])
            ->delete();

        // Save the new selection (null means deselected/skipped)
        if ($validated['answer_id']) {
            StudentAnswer::create([
                'attempt_id' => $attempt->attempt_id,
                'question_id' => $validated['question_id'],
                'selected_answer_id' => $validated['answer_id'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * ============================================
     * MANUAL SUBMIT (Student clicks "Submit Quiz")
     * ============================================
     *
     * Called when the student finishes early and clicks the submit button.
     * Marks the attempt as submitted (not auto-submit) and triggers grading.
     *
     * POST /quizzes/{quiz}/submit
     *
     * Security:
     *   - Only the attempt owner can submit
     *   - Cannot submit twice
     */
    public function submitQuiz(Request $request, Quiz $quiz)
    {
        $user = Auth::user();

        $attempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->firstOrFail();

        if ($attempt->submit_time) {
            return response()->json(
                ['error' => 'Quiz already submitted.'],
                400,
            );
        }

        $attempt->update([
            'submit_time' => now(),
            'is_auto_submit' => false,
        ]);

        // Grade the attempt (Person 4 implements grading logic)
        $this->gradeQuiz($attempt);

        return response()->json([
            'success' => true,
            'message' => 'Quiz submitted successfully.',
            'redirect' => route('quizzes.result', $quiz->quiz_id),
        ]);
    }

    /**
     * ============================================
     * AUTO-SUBMIT (Timer expired)
     * ============================================
     *
     * Called when:
     *   1. The JavaScript countdown reaches 0 and triggers auto-submit
     *   2. A student tries to load the quiz page after time has expired
     *
     * Marks the attempt as auto-submitted, triggers grading, and
     * redirects to the result page.
     *
     * Security:
     *   - Idempotent: if already submitted, redirects to results
     *   - Only the attempt owner is affected
     */
    public function autoSubmit(Quiz $quiz)
    {
        $user = Auth::user();

        $attempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->first();

        if (!$attempt) {
            return redirect()
                ->route('quizzes.announcement', $quiz->quiz_id)
                ->with('error', 'Quiz attempt not found.');
        }

        if ($attempt->submit_time) {
            return redirect()->route('quizzes.result', $quiz->quiz_id);
        }

        $attempt->update([
            'submit_time' => now(),
            'is_auto_submit' => true,
        ]);

        $this->gradeQuiz($attempt);

        return redirect()
            ->route('quizzes.result', $quiz->quiz_id)
            ->with('info', 'Time expired. Quiz was auto-submitted.');
    }

    /**
     * ============================================
     * GET QUIZ STATUS (JSON — polled by JS every second)
     * ============================================
     *
     * Returns real-time status of the quiz/attempt so the JavaScript
     * timer and interface can stay in sync with the server.
     *
     * GET /quizzes/{quiz}/status
     *
     * Response shape:
     * {
     *   has_started: bool,
     *   is_submitted: bool,
     *   time_remaining: int (seconds),
     *   time_until_start: int (seconds, negative if started),
     *   auto_submit_if_expired: bool
     * }
     */
    public function getStatus(Quiz $quiz)
    {
        $user = Auth::user();
        $scheduledTime = Carbon::parse(
            $quiz->scheduled_date . ' ' . $quiz->start_time,
        );
        $now = now();

        $attempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->first();

        if (!$attempt) {
            // No attempt yet — return pre-quiz status
            $hasStarted = $quiz->is_active && $now->isAfter($scheduledTime);

            return response()->json([
                'has_started' => $hasStarted,
                'is_submitted' => false,
                'time_remaining' => 0,
                'time_until_start' => $scheduledTime->diffInSeconds(
                    $now,
                    false,
                ),
                'auto_submit_if_expired' => false,
            ]);
        }

        // Attempt exists — if submitted, tell JS to stop polling
        if ($attempt->submit_time) {
            return response()->json([
                'has_started' => true,
                'is_submitted' => true,
                'time_remaining' => 0,
                'time_until_start' => 0,
                'auto_submit_if_expired' => false,
            ]);
        }

        // Calculate remaining seconds
        $timeLimit = $quiz->duration_minutes * 60;
        $timeElapsed = $now->diffInSeconds($attempt->start_time);
        $timeRemaining = max(0, $timeLimit - $timeElapsed);

        return response()->json([
            'has_started' => true,
            'is_submitted' => false,
            'time_remaining' => $timeRemaining,
            'time_until_start' => $scheduledTime->diffInSeconds($now, false),
            'auto_submit_if_expired' => $timeRemaining <= 0,
        ]);
    }

    /**
     * ============================================
     * SHOW QUIZ RESULT (Stub — Person 4/5 fleshes this out)
     * ============================================
     *
     * Displays the post-submission result page.
     * Currently shows basic score info.
     * Person 4 (automatic grading) and Person 5 (results display)
     * will flesh this out with detailed breakdowns, charts, etc.
     */
    public function showResult(Quiz $quiz)
    {
        $user = Auth::user();

        $attempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->firstOrFail();

        $grade = $attempt->grade;

        $questions = $quiz
            ->questions()
            ->with('answers')
            ->orderBy('question_order')
            ->get();

        $studentAnswers = StudentAnswer::where(
            'attempt_id',
            $attempt->attempt_id,
        )
            ->pluck('selected_answer_id', 'question_id')
            ->toArray();

        return view('quizzes.result', compact(
            'quiz',
            'attempt',
            'grade',
            'questions',
            'studentAnswers',
        ));
    }

    // ============================================
    // PRIVATE HELPERS
    // ============================================

    /**
     * Grade the quiz attempt.
     *
     * Placeholder — Person 4 implements the actual grading algorithm:
     *   - Compare selected_answer_id to each question's correct answer
     *   - Calculate total_score, max_score, percentage
     *   - Apply participation bonuses
     *   - Create/update the Grade record
     *
     * For now this logs the event so Person 4 can pick it up.
     */
    private function gradeQuiz(StudentAttempt $attempt): void
    {
        // Person 4: Insert grading logic here
        \Log::info('Quiz attempt submitted — ready for grading.', [
            'attempt_id' => $attempt->attempt_id,
            'quiz_id' => $attempt->quiz_id,
            'student_id' => $attempt->student_id,
        ]);
    }

    /**
     * Format a seconds value into a human-readable string.
     *
     * Examples:
     *   3661  → "1h 1m 1s"
     *   150   → "2m 30s"
     *   -5    → "Time expired"
     */
    private static function formatTimeRemaining(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'Time expired';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }
        $parts[] = "{$secs}s";

        return implode(' ', $parts);
    }
}
