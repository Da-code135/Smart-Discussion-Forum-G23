<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Quiz;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Send reminder notifications for quizzes that are about to start.
 *
 * Runs every minute via the scheduler (routes/console.php).
 *
 * Logic:
 * 1. Find all published, not-yet-active quizzes
 * 2. For each one, calculate minutes until start
 * 3. If minutes until start equals the configured notification window
 *    (e.g. 15 minutes before), send a reminder to every eligible student
 * 4. Skip students who already received a reminder for this quiz
 */
class SendQuizReminders extends Command
{
    protected $signature = 'quiz:send-reminders';

    protected $description = 'Send reminder notifications for quizzes starting soon';

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        $now = now();

        // Find published, not-yet-active quizzes
        $quizzes = Quiz::where('is_active', false)
            ->whereNotNull('published_at')
            ->with('configuration', 'lecturer')
            ->get();

        $totalRemindersSent = 0;

        foreach ($quizzes as $quiz) {
            $scheduledTime = Carbon::parse(
                $quiz->scheduled_date.' '.$quiz->start_time,
            );

            // Minutes from now until the quiz starts (negative = already past)
            $minutesUntilStart = $now->diffInMinutes($scheduledTime, false);

            // How many minutes before start should we remind?
            $notificationWindow = $quiz->configuration?->notification_minutes_before ?? 15;

            // Only send when the quiz is EXACTLY within the notification window.
            // We use a range: within the window AND not yet past the start time.
            // "Within the window" means: minutesUntilStart <= window AND minutesUntilStart > (window - 1)
            // This avoids sending a reminder on every single run.
            if ($minutesUntilStart <= 0 || $minutesUntilStart > $notificationWindow) {
                continue;
            }

            // Only send once — when minutesUntilStart is in the range
            // (notificationWindow - 0.5) to notificationWindow, rounded.
            // This gives roughly a 60-second window to catch it once.
            $inWindow = $minutesUntilStart <= $notificationWindow
                && $minutesUntilStart > ($notificationWindow - 1);

            if (! $inWindow) {
                continue;
            }

            // Find target users
            $targetUsers = User::whereHas('role', function ($query) use ($quiz) {
                $query->where('role_name', $quiz->target_category);
            })
                ->where('group_id', $quiz->lecturer->group_id)
                ->where('account_status', 'active')
                ->get();

            $remindersSent = 0;

            foreach ($targetUsers as $user) {
                // Skip if already reminded for this quiz
                $alreadyReminded = Notification::where('user_id', $user->id)
                    ->where('type', 'quiz_reminder')
                    ->where('data->quiz_id', $quiz->quiz_id)
                    ->exists();

                if ($alreadyReminded) {
                    continue;
                }

                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'quiz_reminder',
                    'data' => [
                        'quiz_id' => $quiz->quiz_id,
                        'title' => $quiz->title,
                        'minutes_until_start' => $minutesUntilStart,
                        'scheduled_date' => $quiz->scheduled_date,
                        'start_time' => $quiz->start_time,
                        'duration_minutes' => $quiz->duration_minutes,
                        'lecturer_name' => $quiz->lecturer->full_name ?? 'Lecturer',
                    ],
                    'read_at' => null,
                ]);

                $remindersSent++;
            }

            if ($remindersSent > 0) {
                $this->info(
                    "Quiz '{$quiz->title}' (ID: {$quiz->quiz_id}): "
                    ."{$remindersSent} reminder(s) sent ({$minutesUntilStart} min before start)."
                );
            }

            $totalRemindersSent += $remindersSent;
        }

        if ($totalRemindersSent === 0) {
            $this->info('No reminders needed at this time.');
        }

        return self::SUCCESS;
    }
}
