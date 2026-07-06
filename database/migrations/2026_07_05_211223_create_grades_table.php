<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id('grade_id');

            // Foreign keys
            $table->foreignId('attempt_id')
                ->constrained('student_attempts', 'attempt_id')
                ->onDelete('cascade');

            $table->foreignId('student_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('quiz_id')
                ->constrained('quizzes', 'quiz_id')
                ->onDelete('cascade');

            // Scores
            $table->decimal('total_score', 5, 2);  // Points earned (e.g., 45.50)
            $table->decimal('max_score', 5, 2);  // Total possible points (e.g., 100.00)
            $table->decimal('percentage', 5, 2)->nullable();  // Calculated percentage

            // Participation bonus
            $table->decimal('participation_mark', 5, 2)->default(0);  // Extra marks for participation

            // Final grade
            $table->decimal('final_grade', 5, 2)->nullable();  // total_score + participation_mark

            $table->timestamp('graded_at');  // When grading was calculated

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
