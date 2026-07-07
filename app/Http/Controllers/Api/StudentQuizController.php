<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Quiz;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentQuizController extends Controller
{
    /**
     * GET /api/v1/quizzes/{quiz}/announcement
     * Show quiz announcement with timing info (pre-quiz landing).
     */
    public function announcement(Quiz $quiz)
    {
        $user = Auth::user();

        // Group isolation: only users in the quiz's group can access
        if ($quiz->group_id && $quiz->group_id !== $user->group_id && ! $user->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'This quiz is not available for your group.',
            ], 403);
        }

        // Role gate: only users matching the quiz target category can see it
        if ($user->role->role_name !== $quiz->target_category) {
            return response()->json([
                'success' => false,
                'message' => 'This quiz is not available for your role.',
            ], 403);
        }

        // Must be published
        if (! $quiz->published_at) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz announcement not published yet.',
            ], 404);
        }

        $scheduledTime = $quiz->getScheduledDateTime();
        $now = now();
        $timeUntilStart = $scheduledTime->diffInSeconds($now, false);

        return response()->json([
            'success' => true,
            'data' => [
                'quiz' => [
                    'id' => $quiz->quiz_id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'duration_minutes' => $quiz->duration_minutes,
                    'question_count' => $quiz->questions()->count(),
                ],
                'timing' => [
                    'scheduled_time' => $scheduledTime->toIso8601String(),
                    'time_until_start_seconds' => $timeUntilStart,
                    'has_started' => $timeUntilStart <= 0,
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/quizzes/{quiz}/attempt
     * Start a quiz attempt. Creates a StudentAttempt record and returns questions.
     */
    public function start(Quiz $quiz)
    {
        $user = Auth::user();

        // Group isolation: only users in the quiz's group can start
        if ($quiz->group_id && $quiz->group_id !== $user->group_id && ! $user->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'This quiz is not available for your group.',
            ], 403);
        }

        // Role gate
        if ($user->role->role_name !== $quiz->target_category) {
            return response()->json([
                'success' => false,
                'message' => 'This quiz is not available for your role.',
            ], 403);
        }

        // Quiz must be active
        if (! $quiz->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz is not currently active.',
            ], 403);
        }

        // Check existing attempt
        $existingAttempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->first();

        if ($existingAttempt) {
            return response()->json([
                'success' => false,
                'message' => 'You have already started this quiz.',
            ], 409);
        }

        $scheduledTime = $quiz->getScheduledDateTime();
        $isLate = now()->isAfter($scheduledTime);

        // Create attempt
        $attempt = StudentAttempt::create([
            'quiz_id' => $quiz->quiz_id,
            'student_id' => $user->id,
            'start_time' => now(),
            'is_late' => $isLate,
            'submit_time' => null,
            'is_auto_submit' => false,
        ]);

        // Load questions without exposing correct answers
        $questions = $quiz->questions()
            ->with('answers')
            ->orderBy('question_order')
            ->get()
            ->map(function ($question) {
                return [
                    'question_id' => $question->question_id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'marks' => $question->marks,
                    'question_order' => $question->question_order,
                    'answers' => $question->answers->map(function ($answer) {
                        return [
                            'answer_id' => $answer->answer_id,
                            'answer_text' => $answer->answer_text,
                        ];
                    }),
                ];
            });

        $timeRemaining = max(0, ($quiz->duration_minutes * 60) - now()->diffInSeconds($attempt->start_time));

        return response()->json([
            'success' => true,
            'data' => [
                'attempt' => [
                    'attempt_id' => $attempt->attempt_id,
                    'start_time' => $attempt->start_time->toIso8601String(),
                    'is_late' => $attempt->is_late,
                ],
                'questions' => $questions,
                'time_remaining_seconds' => (int) $timeRemaining,
            ],
        ], 201);
    }

    /**
     * GET /api/v1/quizzes/{quiz}/attempt
     * Show the current attempt state (questions, answers, time remaining).
     */
    public function showAttempt(Quiz $quiz)
    {
        $user = Auth::user();

        // Group isolation: only users in the quiz's group can view their attempt
        if ($quiz->group_id && $quiz->group_id !== $user->group_id && ! $user->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this quiz.',
            ], 403);
        }

        $attempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->first();

        if (! $attempt) {
            return response()->json([
                'success' => false,
                'message' => 'No attempt found for this quiz.',
            ], 404);
        }

        // Load questions without exposing correct answers
        $questions = $quiz->questions()
            ->with('answers')
            ->orderBy('question_order')
            ->get()
            ->map(function ($question) {
                return [
                    'question_id' => $question->question_id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'marks' => $question->marks,
                    'question_order' => $question->question_order,
                    'answers' => $question->answers->map(function ($answer) {
                        return [
                            'answer_id' => $answer->answer_id,
                            'answer_text' => $answer->answer_text,
                        ];
                    }),
                ];
            });

        // Load existing student answers keyed by question_id
        $studentAnswers = StudentAnswer::where('attempt_id', $attempt->attempt_id)
            ->pluck('selected_answer_id', 'question_id');

        $timeRemaining = 0;
        if ($attempt->submit_time) {
            $timeRemaining = 0;
        } else {
            $timeRemaining = max(0, ($quiz->duration_minutes * 60) - now()->diffInSeconds($attempt->start_time));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'attempt' => [
                    'attempt_id' => $attempt->attempt_id,
                    'start_time' => $attempt->start_time->toIso8601String(),
                    'submit_time' => $attempt->submit_time?->toIso8601String(),
                    'is_late' => $attempt->is_late,
                    'is_auto_submit' => $attempt->is_auto_submit,
                    'is_submitted' => (bool) $attempt->submit_time,
                ],
                'questions' => $questions,
                'student_answers' => $studentAnswers,
                'time_remaining_seconds' => (int) $timeRemaining,
            ],
        ]);
    }

    /**
     * POST /api/v1/quizzes/{quiz}/answer
     * Save a single answer for the current attempt.
     */
    public function saveAnswer(Request $request, Quiz $quiz)
    {
        $user = Auth::user();

        $request->validate([
            'question_id' => 'required|exists:questions,question_id',
            'answer_id' => 'nullable|exists:answers,answer_id',
        ]);

        $attempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->firstOrFail();

        if ($attempt->submit_time) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz already submitted. Cannot change answers.',
            ], 422);
        }

        // Upsert: delete existing answer for this question, then insert new one
        StudentAnswer::where('attempt_id', $attempt->attempt_id)
            ->where('question_id', $request->question_id)
            ->delete();

        if ($request->answer_id) {
            StudentAnswer::create([
                'attempt_id' => $attempt->attempt_id,
                'question_id' => $request->question_id,
                'selected_answer_id' => $request->answer_id,
            ]);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * POST /api/v1/quizzes/{quiz}/answers/batch
     * Save multiple answers at once.
     */
    public function saveAnswersBatch(Request $request, Quiz $quiz)
    {
        $user = Auth::user();

        $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|exists:questions,question_id',
            'answers.*.answer_id' => 'nullable|exists:answers,answer_id',
        ]);

        $attempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->firstOrFail();

        if ($attempt->submit_time) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz already submitted. Cannot change answers.',
            ], 422);
        }

        DB::transaction(function () use ($attempt, $request) {
            // Delete all existing answers for this attempt
            StudentAnswer::where('attempt_id', $attempt->attempt_id)->delete();

            // Batch insert new answers
            $records = [];
            foreach ($request->answers as $answerData) {
                if ($answerData['answer_id'] !== null) {
                    $records[] = [
                        'attempt_id' => $attempt->attempt_id,
                        'question_id' => $answerData['question_id'],
                        'selected_answer_id' => $answerData['answer_id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($records)) {
                StudentAnswer::insert($records);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Answers saved',
        ]);
    }

    /**
     * POST /api/v1/quizzes/{quiz}/submit
     * Manually submit the quiz attempt.
     */
    public function submit(Request $request, Quiz $quiz)
    {
        $user = Auth::user();

        $attempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->firstOrFail();

        if ($attempt->submit_time) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz already submitted.',
            ], 422);
        }

        $attempt->update([
            'submit_time' => now(),
            'is_auto_submit' => false,
        ]);

        $this->gradeQuiz($attempt);

        return response()->json([
            'success' => true,
            'data' => [
                'attempt' => [
                    'attempt_id' => $attempt->attempt_id,
                    'submit_time' => $attempt->fresh()->submit_time->toIso8601String(),
                    'is_auto_submit' => false,
                ],
            ],
            'message' => 'Quiz submitted successfully',
        ]);
    }

    /**
     * POST /api/v1/quizzes/{quiz}/auto-submit
     * Auto-submit when timer expires.
     */
    public function autoSubmit(Request $request, Quiz $quiz)
    {
        $user = Auth::user();

        $attempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->first();

        if (! $attempt) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz attempt not found.',
            ], 404);
        }

        if ($attempt->submit_time) {
            return response()->json([
                'success' => true,
                'data' => [
                    'attempt' => [
                        'attempt_id' => $attempt->attempt_id,
                        'submit_time' => $attempt->submit_time->toIso8601String(),
                        'is_auto_submit' => $attempt->is_auto_submit,
                    ],
                ],
                'message' => 'Quiz already submitted.',
            ]);
        }

        $attempt->update([
            'submit_time' => now(),
            'is_auto_submit' => true,
        ]);

        $this->gradeQuiz($attempt);

        return response()->json([
            'success' => true,
            'data' => [
                'attempt' => [
                    'attempt_id' => $attempt->attempt_id,
                    'submit_time' => $attempt->fresh()->submit_time->toIso8601String(),
                    'is_auto_submit' => true,
                ],
            ],
            'message' => 'Time expired. Quiz auto-submitted.',
        ]);
    }

    /**
     * GET /api/v1/quizzes/{quiz}/status
     * Real-time quiz status for JS polling.
     */
    public function status(Quiz $quiz)
    {
        $user = Auth::user();

        $attempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->first();

        if (! $attempt) {
            $scheduledTime = $quiz->getScheduledDateTime();
            $hasStarted = now()->isAfter($scheduledTime);

            return response()->json([
                'success' => true,
                'data' => [
                    'has_started' => $hasStarted,
                    'is_submitted' => false,
                    'time_remaining' => null,
                    'time_until_start' => $scheduledTime->diffInSeconds(now(), false),
                ],
            ]);
        }

        if ($attempt->submit_time) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_started' => true,
                    'is_submitted' => true,
                    'submitted_at' => $attempt->submit_time->toIso8601String(),
                    'time_remaining' => 0,
                ],
            ]);
        }

        $timeRemaining = max(0, ($quiz->duration_minutes * 60) - now()->diffInSeconds($attempt->start_time));

        return response()->json([
            'success' => true,
            'data' => [
                'has_started' => true,
                'is_submitted' => false,
                'time_remaining' => (int) $timeRemaining,
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  Grading Logic (reused from Blade controller)
    // ──────────────────────────────────────────────

    /**
     * Grade the quiz attempt, calculate score, and persist Grade record.
     */
    private function gradeQuiz(StudentAttempt $attempt): void
    {
        $quiz = $attempt->quiz;

        $questions = $quiz->questions()->with('answers')->get();

        $studentAnswers = StudentAnswer::where('attempt_id', $attempt->attempt_id)
            ->get()
            ->keyBy('question_id');

        $totalScore = 0;
        $maxScore = 0;

        foreach ($questions as $question) {
            $maxScore += $question->marks;

            $studentAnswer = $studentAnswers->get($question->question_id);

            if (! $studentAnswer || ! $studentAnswer->selected_answer_id) {
                continue;
            }

            $correctAnswer = $question->answers->firstWhere('is_correct', true);

            if ($correctAnswer && (int) $studentAnswer->selected_answer_id === (int) $correctAnswer->answer_id) {
                $totalScore += $question->marks;
            }
        }

        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        $participationMark = $this->calculateParticipationMark($quiz, $percentage);

        $finalGrade = round($totalScore + $participationMark, 2);

        Grade::updateOrCreate(
            ['attempt_id' => $attempt->attempt_id],
            [
                'student_id' => $attempt->student_id,
                'quiz_id' => $quiz->quiz_id,
                'total_score' => $totalScore,
                'max_score' => $maxScore,
                'percentage' => $percentage,
                'participation_mark' => $participationMark,
                'final_grade' => $finalGrade,
                'graded_at' => now(),
            ]
        );
    }

    /**
     * Calculate participation mark based on quiz configuration.
     *
     * - score >= 80% : full marks (5 points)
     * - score >= 50% : half marks (2.5 points)
     * - score < 50%  : 0 marks
     * - If participation_criteria contains 'attempted' : full marks regardless
     */
    private function calculateParticipationMark(Quiz $quiz, float $percentage): float
    {
        $config = $quiz->configuration;
        $fullMarks = 5.0;

        if ($config && $config->participation_criteria) {
            $criteria = strtolower($config->participation_criteria);

            if (str_contains($criteria, 'attempted')) {
                return $fullMarks;
            }
        }

        if ($percentage >= 80) {
            return $fullMarks;
        } elseif ($percentage >= 50) {
            return $fullMarks / 2;
        }

        return 0;
    }
}
