<?php

use App\Models\Quiz;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$quiz = Quiz::find(1);
$future = now()->addMinutes(5);
$quiz->scheduled_date = $future->format('Y-m-d');
$quiz->start_time = $future->format('H:i');
$quiz->save();

echo 'Quiz will start at: '.$quiz->scheduled_date.' '.$quiz->start_time."\n";
