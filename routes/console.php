<?php

use Illuminate\Support\Facades\Schedule;

// Schedule activity monitoring to run daily at 2:00 AM UTC
Schedule::command('monitor:activity')->daily()->at('02:00');

// Send reminders for quizzes that are about to start (checks every minute)
Schedule::command('quiz:send-reminders')->everyMinute();

// Activate quizzes at their scheduled start time (checks every minute)
Schedule::command('quiz:activate')->everyMinute();
