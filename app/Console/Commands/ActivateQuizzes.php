<?php

namespace App\Console\Commands;

use App\Events\QuizWentLive;
use App\Models\Quiz;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Activate quizzes at their scheduled start time.
 *
 * Runs every minute via the scheduler (routes/console.php).
 *
 * Logic:
 * 1. Find all published, not-yet-active quizzes
 * 2. For each one, check if the scheduled date+time has arrived
 * 3. If yes: flip is_active to true and dispatch QuizWentLive
 *
 * This is what makes the quiz available to students. Without this,
 * Person 3's showQuiz() would reject entry because is_active is
 * still false.
 */
class ActivateQuizzes extends Command
{
    protected $signature = 'quiz:activate';

    protected $description = 'Activate quizzes at their scheduled start time';

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        $now = now();

        // Find published, not-yet-active quizzes
        $quizzes = Quiz::where('is_active', false)
            ->whereNotNull('published_at')
            ->get();

        $activated = 0;

        foreach ($quizzes as $quiz) {
            $scheduledTime = $quiz->getScheduledDateTime();

            // Has the scheduled time arrived or passed?
            if ($now->isAfter($scheduledTime)) {
                // Activate the quiz
                $quiz->update(['is_active' => true]);

                // Dispatch event — NotifyQuizLive listener will
                // send "Quiz is live!" notifications to students
                QuizWentLive::dispatch($quiz);

                $this->info(
                    "Quiz '{$quiz->title}' (ID: {$quiz->quiz_id}) activated."
                );

                $activated++;
            }
        }

        if ($activated === 0) {
            $this->info('No quizzes to activate at this time.');
        }

        return self::SUCCESS;
    }
}
