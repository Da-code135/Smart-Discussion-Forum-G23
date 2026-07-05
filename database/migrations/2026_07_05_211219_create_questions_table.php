<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id('question_id');
            
            // Foreign key to quiz
            $table->foreignId('quiz_id')
                  ->constrained('quizzes', 'quiz_id')
                  ->onDelete('cascade');  // If quiz deleted, delete all its questions
            
            // Question content
            $table->text('question_text');  // The actual question
            
            // Question type
            $table->enum('question_type', ['MCQ', 'TF', 'Short'])
                  ->default('MCQ');  
            // MCQ = Multiple Choice
            // TF = True/False
            // Short = Short answer (for future)
            
            // Marking
            $table->integer('marks')->default(1);  // How many marks this question is worth
            
            // Order
            $table->integer('question_order');  // Display order in quiz (1, 2, 3...)
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};