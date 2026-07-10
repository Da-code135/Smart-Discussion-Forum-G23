<?php

namespace App\Listeners;

use App\Events\QuizPublished;
use App\Models\Notification;
use App\Models\User;

/**
 * When a quiz is published (announced), notify every student whose
 * role matches the quiz's target_category within the same group.
 */
class SendQuizAnnouncement
{
    /**
     * Handle the event.
     *
     * Step by step:
     * 1. Get the quiz from the event
     * 2. Find all users whose role_name matches the quiz's target_category
     *    AND who belong to the same group as the lecturer
     * 3. Create a notification record for each one
     * 4. Log how many were sent
     */
    public function handle(QuizPublished $event): void
    {
        $quiz = $event->quiz;

        // Find target users: matching role + same group as the quiz (not the lecturer's group)
        $targetUsers = User::whereHas('role', function ($query) use ($quiz) {
            $query->where('role_name', $quiz->target_category);
        })
            ->where('account_status', 'active');

        // Scope to the quiz's group if one is set (null group_id = platform-wide quiz)
        if ($quiz->group_id) {
            $targetUsers->where('group_id', $quiz->group_id);
        }

        $targetUsers = $targetUsers->get();

        $count = 0;

        foreach ($targetUsers as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'quiz_announcement',
                'data' => [
                    'quiz_id' => $quiz->quiz_id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'scheduled_date' => $quiz->scheduled_date,
                    'start_time' => $quiz->start_time,
                    'duration_minutes' => $quiz->duration_minutes,
                    'lecturer_name' => $quiz->lecturer->full_name ?? 'Lecturer',
                ],
                'read_at' => null,
            ]);

            $count++;
        }

        logger("Quiz {$quiz->quiz_id} announcement sent to {$count} user(s) with role '{$quiz->target_category}'.");
    }
}
