<?php

namespace App\Listeners;

use App\Events\QuizWentLive;
use App\Models\Notification;
use App\Models\User;

/**
 * When a quiz goes live (is_active flipped to true), notify every
 * eligible student that the quiz is now available.
 */
class NotifyQuizLive
{
    /**
     * Handle the event.
     *
     * Step by step:
     * 1. Get the quiz from the event
     * 2. Find all active users whose role matches target_category
     *    and who share the lecturer's group
     * 3. Skip users who already have a "quiz_live" notification for
     *    this quiz (prevents duplicates on reactivation)
     * 4. Create a notification record for each remaining user
     * 5. Log the result
     */
    public function handle(QuizWentLive $event): void
    {
        $quiz = $event->quiz;

        // Find target users: matching role + same group as the lecturer
        $targetUsers = User::whereHas('role', function ($query) use ($quiz) {
            $query->where('role_name', $quiz->target_category);
        })
            ->where('group_id', $quiz->lecturer->group_id)
            ->where('account_status', 'active')
            ->get();

        $count = 0;

        foreach ($targetUsers as $user) {
            // Check for existing live notification for this quiz
            $alreadyNotified = Notification::where('user_id', $user->id)
                ->where('type', 'quiz_live')
                ->where('data->quiz_id', $quiz->quiz_id)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            Notification::create([
                'user_id' => $user->id,
                'type' => 'quiz_live',
                'data' => [
                    'quiz_id' => $quiz->quiz_id,
                    'title' => $quiz->title,
                    'duration_minutes' => $quiz->duration_minutes,
                    'lecturer_name' => $quiz->lecturer->full_name ?? 'Lecturer',
                ],
                'read_at' => null,
            ]);

            $count++;
        }

        logger("Quiz {$quiz->quiz_id} is now live! Notified {$count} user(s).");
    }
}
