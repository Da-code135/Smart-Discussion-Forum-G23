<?php

namespace App\Events;

use App\Models\Quiz;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a quiz is activated (goes live) at its scheduled time.
 *
 * This tells the system: "The quiz is now live — students can start
 * taking it. Notify them."
 *
 * The corresponding listener (NotifyQuizLive) picks this up and
 * sends a "Quiz is live NOW!" notification to all eligible students.
 *
 * The activation itself is done by the ActivateQuizzes scheduled
 * command, which runs every minute and flips is_active to true
 * for any quiz whose scheduled time has arrived.
 */
class QuizWentLive
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Quiz $quiz)
    {
        //
    }
}
