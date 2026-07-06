<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;

class GradeController extends Controller
{
    /**
     * GET /api/v1/quizzes/{quiz}/result
     *
     * Student-facing: returns the authenticated student's grade for a quiz,
     * with a per-question breakdown (question text, selected answer, correct
     * answer if the config permits, marks earned, marks possible).
     *
     * Gated behind show_results_after_close — if the quiz config forbids it,
     * the student gets a 403.
     */
    public function myResult(Quiz $quiz)
    {
        $user = Auth::user();

        $grade = Grade::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $user->id)
            ->firstOrFail();

        // Gate: results may be hidden until the lecturer opens them
        $config = $quiz->configuration;
        if ($config && !$config->show_results_after_close) {
            return response()->json([
                'success' => false,
                'message' => 'Results are not yet available.',
            ], 403);
        }

        // Load the attempt with every student answer and its linked question + choices
        $grade->load([
            'attempt.answers.question.answers',
            'attempt.answers.selectedAnswer',
        ]);

        $attempt = $grade->attempt;
        $questions = $quiz->questions()->with('answers')->orderBy('question_order')->get();

        $studentAnswers = $attempt->answers->keyBy('question_id');

        $breakdown = $questions->map(function ($question) use ($studentAnswers, $config) {
            $studentAnswer = $studentAnswers->get($question->question_id);
            $correctAnswer = $question->answers->firstWhere('is_correct', true);

            $marksEarned = 0;
            if ($studentAnswer && $studentAnswer->selected_answer_id) {
                if ($correctAnswer && (int) $studentAnswer->selected_answer_id === (int) $correctAnswer->answer_id) {
                    $marksEarned = $question->marks;
                }
            }

            $item = [
                'question_id'    => $question->question_id,
                'question_text'  => $question->question_text,
                'question_type'  => $question->question_type,
                'your_answer'    => $studentAnswer && $studentAnswer->selectedAnswer
                    ? [
                        'answer_id'   => $studentAnswer->selectedAnswer->answer_id,
                        'answer_text' => $studentAnswer->selectedAnswer->answer_text,
                    ]
                    : null,
                'marks_earned'   => $marksEarned,
                'marks_possible' => $question->marks,
            ];

            // Only expose the correct answer when the quiz config allows it
            if ($config && $config->show_correct_answers && $correctAnswer) {
                $item['correct_answer'] = [
                    'answer_id'   => $correctAnswer->answer_id,
                    'answer_text' => $correctAnswer->answer_text,
                ];
            }

            return $item;
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'grade'     => [
                    'grade_id'           => $grade->grade_id,
                    'total_score'        => $grade->total_score,
                    'max_score'          => $grade->max_score,
                    'percentage'         => $grade->percentage,
                    'participation_mark' => $grade->participation_mark,
                    'final_grade'        => $grade->final_grade,
                    'graded_at'          => $grade->graded_at?->toIso8601String(),
                ],
                'questions' => $breakdown,
                'config'    => [
                    'show_correct_answers' => $config && $config->show_correct_answers,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/lecturer/quizzes/{quiz}/grades
     *
     * Lecturer/admin: list all grades for a given quiz.
     */
    public function index(Quiz $quiz)
    {
        $quiz->load('grades.student:id,full_name,email');

        return response()->json([
            'success' => true,
            'data'    => [
                'grades' => $quiz->grades->map(function (Grade $grade) {
                    return [
                        'grade_id'           => $grade->grade_id,
                        'student_id'         => $grade->student_id,
                        'student_name'       => $grade->student?->full_name,
                        'student_email'      => $grade->student?->email,
                        'total_score'        => $grade->total_score,
                        'max_score'          => $grade->max_score,
                        'percentage'         => $grade->percentage,
                        'participation_mark' => $grade->participation_mark,
                        'final_grade'        => $grade->final_grade,
                        'graded_at'          => $grade->graded_at?->toIso8601String(),
                    ];
                }),
                'quiz'   => [
                    'id'        => $quiz->quiz_id,
                    'title'     => $quiz->title,
                    'max_score' => $quiz->questions()->sum('marks'),
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/lecturer/grades/{grade}
     *
     * Lecturer/admin: detailed view of a single grade with
     * a per-question breakdown.
     */
    public function show(Grade $grade)
    {
        $grade->load([
            'attempt.answers.question.answers',
            'attempt.answers.selectedAnswer',
            'student:id,full_name,email',
            'quiz',
        ]);

        $attempt = $grade->attempt;
        $quiz = $grade->quiz;

        if ($attempt && $quiz) {
            $questions = $quiz->questions()->with('answers')->orderBy('question_order')->get();
            $studentAnswers = $attempt->answers->keyBy('question_id');

            $breakdown = $questions->map(function ($question) use ($studentAnswers) {
                $studentAnswer = $studentAnswers->get($question->question_id);
                $correctAnswer = $question->answers->firstWhere('is_correct', true);

                $marksEarned = 0;
                if ($studentAnswer && $studentAnswer->selected_answer_id) {
                    if ($correctAnswer && (int) $studentAnswer->selected_answer_id === (int) $correctAnswer->answer_id) {
                        $marksEarned = $question->marks;
                    }
                }

                return [
                    'question_id'    => $question->question_id,
                    'question_text'  => $question->question_text,
                    'question_type'  => $question->question_type,
                    'your_answer'    => $studentAnswer && $studentAnswer->selectedAnswer
                        ? [
                            'answer_id'   => $studentAnswer->selectedAnswer->answer_id,
                            'answer_text' => $studentAnswer->selectedAnswer->answer_text,
                        ]
                        : null,
                    'correct_answer' => $correctAnswer
                        ? [
                            'answer_id'   => $correctAnswer->answer_id,
                            'answer_text' => $correctAnswer->answer_text,
                        ]
                        : null,
                    'marks_earned'   => $marksEarned,
                    'marks_possible' => $question->marks,
                ];
            });
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'grade'     => [
                    'grade_id'           => $grade->grade_id,
                    'student_id'         => $grade->student_id,
                    'student_name'       => $grade->student?->full_name,
                    'student_email'      => $grade->student?->email,
                    'quiz_id'            => $quiz?->quiz_id,
                    'quiz_title'         => $quiz?->title,
                    'total_score'        => $grade->total_score,
                    'max_score'          => $grade->max_score,
                    'percentage'         => $grade->percentage,
                    'participation_mark' => $grade->participation_mark,
                    'final_grade'        => $grade->final_grade,
                    'graded_at'          => $grade->graded_at?->toIso8601String(),
                ],
                'breakdown' => $breakdown ?? [],
            ],
        ]);
    }

    /**
     * GET /api/v1/lecturer/quizzes/{quiz}/grades/export
     *
     * Lecturer/admin: stream a CSV file of all grades for the quiz.
     */
    public function exportCsv(Quiz $quiz)
    {
        $quiz->load('grades.student:id,full_name,email');

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="quiz-' . $quiz->quiz_id . '-grades.csv"',
        ];

        $callback = function () use ($quiz) {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'Student Name', 'Email', 'Score', 'Max',
                'Percentage', 'Participation', 'Final Grade',
            ]);

            // Data rows
            foreach ($quiz->grades as $grade) {
                fputcsv($handle, [
                    $grade->student?->full_name ?? 'N/A',
                    $grade->student?->email ?? 'N/A',
                    $grade->total_score,
                    $grade->max_score,
                    $grade->percentage,
                    $grade->participation_mark,
                    $grade->final_grade,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
