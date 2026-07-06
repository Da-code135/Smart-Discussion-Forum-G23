<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_answers', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('attempt_id')
                ->constrained('student_attempts', 'attempt_id')
                ->onDelete('cascade');

            $table->foreignId('question_id')
                ->constrained('questions', 'question_id')
                ->onDelete('cascade');

            // Student's response
            $table->foreignId('selected_answer_id')
                ->nullable()
                ->constrained('answers', 'answer_id')
                ->onDelete('set null');  // If answer deleted, set to NULL (student skipped)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_answers');
    }
};
