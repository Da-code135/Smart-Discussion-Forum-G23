<?php

namespace App\Events;

use App\Models\Quiz;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a lecturer publishes (announces) a quiz.
 *
 * This tells the system: "A quiz has been announced — go notify the
 * students who need to know about it."
 *
 * The corresponding listener (SendQuizAnnouncement) picks this up and
 * creates notification records for every student whose role matches
 * the quiz's target_category.
 */
class QuizPublished
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
