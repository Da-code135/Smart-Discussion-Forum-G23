<?php

namespace App\Http\Controllers;

use App\Events\QuizPublished;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    /**
     * Show list of quizzes scoped to the lecturer's accessible groups.
     *
     * System Admins see all quizzes platform-wide for oversight.
     */
    public function index()
    {
        $user = Auth::user();

        $query = Quiz::with('configuration', 'group', 'lecturer:id,full_name');

        if ($user->isSystemAdmin()) {
            // Platform-wide oversight: every quiz, every group
            $quizzes = $query->latest()->paginate(10);
        } else {
            $accessibleGroupIds = Quiz::lecturerAccessibleGroupIds($user);

            $quizzes = $query->where('lecturer_id', $user->id)
                ->whereIn('group_id', $accessibleGroupIds)
                ->latest()
                ->paginate(10);
        }

        return view('quizzes.index', compact('quizzes'));
    }

    /**
     * Show form to create new quiz
     */
    public function create()
    {
        $user = Auth::user();

        // System Admins can create quizzes for any group; others are limited
        // to their accessible groups (own + taught + administered).
        $accessibleGroupIds = Quiz::lecturerAccessibleGroupIds($user);
        $groups = Group::whereIn('id', $accessibleGroupIds)->orderBy('group_name')->get();

        return view('quizzes.create', compact('groups'));
    }

    /**
     * Store new quiz in database
     *
     * What this does:
     * 1. Validate input (title, date, time, duration)
     * 2. Create Quiz record
     * 3. Create QuizConfiguration with default settings
     * 4. Redirect to add questions
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // System Admins must explicitly pick a group; others can fall back to their own
        $groupRequired = $user->isSystemAdmin();

        // Validate input
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_category' => 'required|in:Student,Lecturer,Administrator,Member',
            'group_id' => $groupRequired ? 'required|integer|exists:groups,id' : 'nullable|integer|exists:groups,id',
            'scheduled_date' => 'required|date|after_or_equal:today',  // Can't schedule in past
            'start_time' => 'required|date_format:H:i',  // Format: 10:30
            'duration_minutes' => 'required|integer|min:1|max:480',  // Max 8 hours
        ]);

        // Determine the group for this quiz
        $group = null;
        if ($request->has('group_id')) {
            $group = Group::findOrFail($validated['group_id']);
            if (! $user->canTeachGroup($group)) {
                abort(403, 'You cannot create quizzes for this group.');
            }
        } elseif ($user->group_id !== null) {
            $group = Group::find($user->group_id);
        }

        // Create the quiz
        $quiz = Quiz::create([
            'lecturer_id' => $user->id,  // Current user is the lecturer
            'group_id' => $group?->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'target_category' => $validated['target_category'],
            'scheduled_date' => $validated['scheduled_date'],
            'start_time' => $validated['start_time'],
            'duration_minutes' => $validated['duration_minutes'],
            'is_active' => false,  // Not live yet
            'published_at' => null,  // Not announced yet
        ]);

        // Create default configuration
        QuizConfiguration::create([
            'quiz_id' => $quiz->quiz_id,
            'allow_late_join' => false,  // Default: don't allow late starters
            'notification_minutes_before' => 15,  // Remind 15 min before
            'lock_screen_on_start' => true,  // Default: lock screen
            'show_results_after_close' => true,  // Default: show results after close
            'show_correct_answers' => false,  // Default: don't show answers
        ]);

        return redirect()->route('quizzes.edit', $quiz->quiz_id)
            ->with('success', 'Quiz created! Now add questions.');
    }

    /**
     * Show form to edit quiz and add questions
     */
    public function edit(Quiz $quiz)
    {
        // Security: Only the quiz creator can edit it
        if ($quiz->lecturer_id !== Auth::id()) {
            abort(403, 'You can only edit your own quizzes.');
        }

        // Load relationships with proper ordering
        $quiz->load([
            'questions' => fn ($q) => $q->orderBy('question_order'),
            'questions.answers',
            'configuration',
        ]);

        return view('quizzes.edit', compact('quiz'));
    }

    /**
     * Update quiz details (but NOT after published)
     *
     * Rule: Can't edit after quiz is published
     * Because students have already seen the announcement
     */
    public function update(Request $request, Quiz $quiz)
    {
        // Only quiz creator can update
        if ($quiz->lecturer_id !== Auth::id()) {
            abort(403, 'You can only edit your own quizzes.');
        }

        // Check if already published
        if ($quiz->published_at) {
            return back()->with('error', 'Cannot edit a published quiz.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_category' => 'required|in:Student,Lecturer,Administrator,Member',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'duration_minutes' => 'required|integer|min:1|max:480',
            'allow_late_join' => 'boolean',
            'lock_screen_on_start' => 'boolean',
            'show_results_after_close' => 'boolean',
            'show_correct_answers' => 'boolean',
            'participation_criteria' => 'nullable|string|max:500',
        ]);

        // Update quiz
        $quiz->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'target_category' => $validated['target_category'],
            'scheduled_date' => $validated['scheduled_date'],
            'start_time' => $validated['start_time'],
            'duration_minutes' => $validated['duration_minutes'],
        ]);

        // Update configuration
	        if ($quiz->configuration) {
	            $quiz->configuration->update([
	                'allow_late_join' => $validated['allow_late_join'] ?? false,
	                'lock_screen_on_start' => $validated['lock_screen_on_start'] ?? false,
	                'show_results_after_close' => $validated['show_results_after_close'] ?? false,
	                'show_correct_answers' => $validated['show_correct_answers'] ?? false,
	                'participation_criteria' => $validated['participation_criteria'],
	            ]);
	        }

        return back()->with('success', 'Quiz updated.');
    }

    /**
     * Delete quiz (only if not published)
     */
    public function destroy(Quiz $quiz)
    {
        // Only quiz creator can delete
        if ($quiz->lecturer_id !== Auth::id()) {
            abort(403, 'You can only delete your own quizzes.');
        }

        if ($quiz->published_at) {
            return back()->with('error', 'Cannot delete a published quiz.');
        }

        $quiz->delete();  // Cascades to questions, answers, etc.

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz deleted.');
    }

    /**
     * Publish quiz as announcement
     *
     * What this does:
     * 1. Check quiz is properly configured (has questions, valid date/time)
     * 2. Set published_at timestamp
     * 3. Create announcement notification for target students
     * 4. Quiz is now visible to students but NOT YET LIVE
     *
     * Important: Publishing does NOT make quiz active
     * Quiz becomes active when scheduled time arrives (Person 4's job)
     */
    public function publish(Request $request, Quiz $quiz)
    {
        // Only quiz creator can publish
        if ($quiz->lecturer_id !== Auth::id()) {
            abort(403, 'You can only publish your own quizzes.');
        }

        // Check if already published
        if ($quiz->published_at) {
            return back()->with('error', 'Quiz already published.');
        }

        // Validation: Quiz must have at least 1 question
        if ($quiz->questions()->count() === 0) {
            return back()->with('error', 'Quiz must have at least 1 question.');
        }

        // Validation: Scheduled date/time must be in future
        $scheduledDateTime = $quiz->getScheduledDateTime();
        if ($scheduledDateTime->isPast()) {
            return back()->with('error', 'Quiz date/time must be in the future.');
        }

        // Mark as published
        $quiz->update(['published_at' => now()]);

        // Dispatch event — SendQuizAnnouncement listener creates
        // notifications for all students in the target audience.
        QuizPublished::dispatch($quiz);

        return back()->with('success', 'Quiz published! Students have been notified.');
    }

    /**
     * Show performance report for a quiz (lecturer/admin only).
     *
     * GET /quizzes/{quiz}/report
     * Route name: quizzes.report
     *
     * Displays class performance summary, student-by-student breakdown,
     * and aggregate statistics.
     */
    public function showPerformanceReport(Quiz $quiz)
    {
        // Security: Only the quiz lecturer or an admin can view
        if (Auth::user()->id !== $quiz->lecturer_id && ! Auth::user()->isAdmin()) {
            abort(403, 'Not authorized to view this report.');
        }

        $grades = Grade::where('quiz_id', $quiz->quiz_id)
            ->with('student')
            ->orderByDesc('final_grade')
            ->get();

        $stats = $this->getClassStatistics($quiz);

        return view('quizzes.performance-report', compact('quiz', 'grades', 'stats'));
    }

    /**
     * Calculate aggregate class statistics for a quiz.
     */
    private function getClassStatistics(Quiz $quiz): ?array
    {
        $allGrades = Grade::where('quiz_id', $quiz->quiz_id)->get();

        if ($allGrades->count() === 0) {
            return null;
        }

        $scores = $allGrades->pluck('total_score')->toArray();

        return [
            'total_attempts' => $allGrades->count(),
            'average_score' => round($allGrades->avg('total_score'), 2),
            'highest_score' => round(max($scores), 2),
            'lowest_score' => round(min($scores), 2),
        ];
    }
}
