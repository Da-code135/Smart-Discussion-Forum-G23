<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Quiz;
use App\Models\StudentAttempt;
use Illuminate\Support\Facades\Auth;

class QuizNotificationController extends Controller
{
    /**
     * GET /api/v1/quizzes/upcoming
     *
     * Return up to 10 published, not-yet-active quizzes scheduled from today onward.
     * Students use this to see what quizzes are coming up.
     */
    public function upcoming()
    {
        $quizzes = Quiz::where('published_at', '!=', null)
            ->where('is_active', false)
            ->whereDate('scheduled_date', '>=', today())
            ->with('lecturer:id,full_name')
            ->orderBy('scheduled_date')
            ->take(10)
            ->get()
            ->map(function (Quiz $quiz) {
                return [
                    'quiz_id'          => $quiz->quiz_id,
                    'title'            => $quiz->title,
                    'description'      => $quiz->description,
                    'target_category'  => $quiz->target_category,
                    'scheduled_date'   => $quiz->scheduled_date?->toDateString(),
                    'start_time'       => $quiz->start_time?->format('H:i'),
                    'duration_minutes' => $quiz->duration_minutes,
                    'lecturer'         => $quiz->lecturer
                        ? [
                            'id'        => $quiz->lecturer->id,
                            'full_name' => $quiz->lecturer->full_name,
                        ]
                        : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => [
                'quizzes' => $quizzes,
            ],
        ]);
    }

    /**
     * GET /api/v1/quizzes/live
     *
     * Return all currently active quizzes (is_active = true).
     * Students use this to see which quizzes are running right now.
     */
    public function live()
    {
        $quizzes = Quiz::where('is_active', true)
            ->with('lecturer:id,full_name')
            ->get()
            ->map(function (Quiz $quiz) {
                return [
                    'quiz_id'          => $quiz->quiz_id,
                    'title'            => $quiz->title,
                    'description'      => $quiz->description,
                    'target_category'  => $quiz->target_category,
                    'scheduled_date'   => $quiz->scheduled_date?->toDateString(),
                    'start_time'       => $quiz->start_time?->format('H:i'),
                    'duration_minutes' => $quiz->duration_minutes,
                    'lecturer'         => $quiz->lecturer
                        ? [
                            'id'        => $quiz->lecturer->id,
                            'full_name' => $quiz->lecturer->full_name,
                        ]
                        : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => [
                'quizzes' => $quizzes,
            ],
        ]);
    }

    /**
     * GET /api/v1/me/quiz-history
     *
     * Paginated list of the authenticated student's past quiz attempts
     * with their associated grade.
     */
    public function history()
    {
        $user = Auth::user();

        $attempts = StudentAttempt::where('student_id', $user->id)
            ->with('quiz:id,title', 'grade')
            ->latest()
            ->paginate(20);

        $attempts->getCollection()->transform(function (StudentAttempt $attempt) {
            return [
                'attempt_id'     => $attempt->attempt_id,
                'quiz_id'        => $attempt->quiz_id,
                'quiz_title'     => $attempt->quiz?->title,
                'start_time'     => $attempt->start_time?->toIso8601String(),
                'submit_time'    => $attempt->submit_time?->toIso8601String(),
                'is_auto_submit' => $attempt->is_auto_submit,
                'is_late'        => $attempt->is_late,
                'grade'          => $attempt->grade
                    ? [
                        'grade_id'    => $attempt->grade->grade_id,
                        'total_score' => $attempt->grade->total_score,
                        'max_score'   => $attempt->grade->max_score,
                        'percentage'  => $attempt->grade->percentage,
                        'final_grade' => $attempt->grade->final_grade,
                    ]
                    : null,
            ];
        });

        return response()->json([
            'success'    => true,
            'data'       => [
                'attempts' => $attempts->items(),
            ],
            'pagination' => [
                'current_page' => $attempts->currentPage(),
                'last_page'    => $attempts->lastPage(),
                'per_page'     => $attempts->perPage(),
                'total'        => $attempts->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/me/quiz-notifications
     *
     * Paginated list of quiz-related notifications for the authenticated user.
     * Matches the types stored by Person 5's listeners and scheduled commands:
     *   - quiz_announcement (published by lecturer)
     *   - quiz_reminder     (sent before quiz starts)
     *   - quiz_live         (quiz is active now)
     */
    public function quizNotifications()
    {
        $user = Auth::user();

        $notifications = Notification::where('user_id', $user->id)
            ->whereIn('type', ['quiz_announcement', 'quiz_reminder', 'quiz_live'])
            ->latest()
            ->paginate(20);

        $notifications->getCollection()->transform(function (Notification $notification) {
            return [
                'id'         => $notification->id,
                'type'       => $notification->type,
                'data'       => $notification->data,
                'read_at'    => $notification->read_at?->toIso8601String(),
                'is_read'    => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success'    => true,
            'data'       => [
                'notifications' => $notifications->items(),
            ],
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/notifications/{id}/read
     *
     * Mark a single notification as read. Only the notification's owner
     * can mark it (checked by scoping to Auth::id()).
     */
    public function markRead(int $id)
    {
        $user = Auth::user();

        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Marked as read',
        ]);
    }
}
