<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attempts', function (Blueprint $table) {
            $table->id('attempt_id');

            // Foreign keys
            $table->foreignId('quiz_id')
                ->constrained('quizzes', 'quiz_id')
                ->onDelete('cascade');

            $table->foreignId('student_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Attempt timing
            $table->timestamp('start_time');  // When student clicked "Start Quiz"
            $table->timestamp('submit_time')->nullable();  // When student submitted (NULL until submitted)

            // Auto-submit tracking
            $table->boolean('is_auto_submit')->default(false);  // TRUE if system auto-submitted on timeout

            // Late joiner tracking
            $table->boolean('is_late')->default(false);  // TRUE if student joined after scheduled start time

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attempts');
    }
};
