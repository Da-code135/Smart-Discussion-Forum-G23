<?php

use Illuminate\Support\Facades\Schedule;

// Schedule activity monitoring to run daily at 2:00 AM UTC
Schedule::command('monitor:activity')->daily()->at('02:00');
