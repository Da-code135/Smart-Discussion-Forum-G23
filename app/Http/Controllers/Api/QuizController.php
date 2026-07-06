<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizConfiguration;
use App\Models\Question;
use App\Models\Grade;
use App\Models\User;
use App\Events\QuizPublished;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class QuizController extends Controller
{
    /**
     * Display a listing of quizzes.
     */
    public function index(Request $request)
    {
        $query = Quiz::withCount('questions')->with('configuration', 'lecturer:id,full_name')->latest();

        // If user is group admin, filter to quizzes where group_id matches their administered groups
        $user = Auth::user();
        if ($user->isGroupAdmin()) {
            $adminGroupIds = $user->administeredGroups()->pluck('groups.id');
            $query->whereHas('lecturer', function ($q) use ($adminGroupIds) {
                $q->whereIn('group_id', $adminGroupIds);
            });
        }

        $quizzes = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'quizzes' => $quizzes,
            ],
            'pagination' => [
                'current_page' => $quizzes->currentPage(),
                'last_page' => $quizzes->lastPage(),
                'per_page' => $quizzes->perPage(),
                'total' => $quizzes->total(),
            ]
        ]);
    }

    /**
     * Store a newly created quiz.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_category' => ['required', Rule::in(['Student', 'Lecturer', 'Administrator', 'Member'])],
            'scheduled_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'duration_minutes' => 'required|integer|min:1|max:480',
        ]);

        $quiz = Quiz::create([
            'lecturer_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'target_category' => $validated['target_category'],
            'scheduled_date' => $validated['scheduled_date'],
            'start_time' => $validated['start_time'],
            'duration_minutes' => $validated['duration_minutes'],
            'is_active' => false,
            'published_at' => null,
        ]);

        // Create default QuizConfiguration
        QuizConfiguration::create([
            'quiz_id' => $quiz->quiz_id,
            'allow_late_join' => false,
            'notification_minutes_before' => 15,
            'lock_screen_on_start' => true,
            'show_results_after_close' => true,
            'show_correct_answers' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'quiz' => $quiz->load('configuration', 'lecturer:id,full_name'),
            ],
            'message' => 'Quiz created'
        ], 201);
    }

    /**
     * Display the specified quiz.
     */
    public function show(Quiz $quiz)
    {
        $quiz->load('questions.answers', 'configuration', 'lecturer:id,full_name');

        return response()->json([
            'success' => true,
            'data' => [
                'quiz' => $quiz,
            ]
        ]);
    }

    /**
     * Update the specified quiz.
     */
    public function update(Request $request, Quiz $quiz)
    {
        if ($quiz->published_at) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a published quiz'
            ], 422);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_category' => ['required', Rule::in(['Student', 'Lecturer', 'Administrator', 'Member'])],
            'scheduled_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'duration_minutes' => 'required|integer|min:1|max:480',
        ]);

        $quiz->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'target_category' => $validated['target_category'],
            'scheduled_date' => $validated['scheduled_date'],
            'start_time' => $validated['start_time'],
            'duration_minutes' => $validated['duration_minutes'],
        ]);

        $quiz->configuration->update([
            'allow_late_join' => $request->input('allow_late_join', $quiz->configuration->allow_late_join),
            'notification_minutes_before' => $request->input('notification_minutes_before', $quiz->configuration->notification_minutes_before),
            'lock_screen_on_start' => $request->input('lock_screen_on_start', $quiz->configuration->lock_screen_on_start),
            'show_results_after_close' => $request->input('show_results_after_close', $quiz->configuration->show_results_after_close),
            'show_correct_answers' => $request->input('show_correct_answers', $quiz->configuration->show_correct_answers),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'quiz' => $quiz->load('configuration', 'lecturer:id,full_name'),
            ],
            'message' => 'Quiz updated'
        ]);
    }

    /**
     * Remove the specified quiz.
     */
    public function destroy(Quiz $quiz)
    {
        if ($quiz->published_at) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a published quiz'
            ], 422);
        }

        $quiz->delete();

        return response()->json([
            'success' => true,
            'message' => 'Quiz deleted'
        ]);
    }

    /**
     * Publish the specified quiz.
     */
    public function publish(Request $request, Quiz $quiz)
    {
        if ($quiz->published_at) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz is already published'
            ], 422);
        }

        if ($quiz->questions()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot publish a quiz with no questions'
            ], 422);
        }

        $scheduledDateTime = Carbon::parse($quiz->scheduled_date . ' ' . $quiz->start_time);
        if ($scheduledDateTime->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot publish a quiz with a past scheduled date/time'
            ], 422);
        }

        $quiz->update(['published_at' => now()]);
        
        // Dispatch event
        event(new QuizPublished($quiz));

        return response()->json([
            'success' => true,
            'data' => [
                'quiz' => $quiz->load('configuration', 'lecturer:id,full_name'),
            ],
            'message' => 'Quiz published'
        ]);
    }

    /**
     * Generate report for the specified quiz.
     */
    public function report(Request $request, Quiz $quiz)
    {
        $quiz->load('grades.student:id,full_name,email');

        $gradesCollection = $quiz->grades;
        
        $avgScore = $gradesCollection->avg('percentage');
        $maxScore = $gradesCollection->max('percentage');
        $minScore = $gradesCollection->min('percentage');
        $attemptCount = $gradesCollection->count();

        $summary = [
            'average_score' => $avgScore,
            'max_score' => $maxScore,
            'min_score' => $minScore,
            'attempt_count' => $attemptCount,
        ];

        $students = $gradesCollection->map(function ($grade) {
            return [
                'student' => $grade->student,
                'total_score' => $grade->total_score,
                'max_score' => $grade->max_score,
                'percentage' => $grade->percentage,
                'participation_mark' => $grade->participation_mark,
                'final_grade' => $grade->final_grade,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'students' => $students,
            ]
        ]);
    }
}