<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id('answer_id');
            
            // Foreign key to question
            $table->foreignId('question_id')
                  ->constrained('questions', 'question_id')
                  ->onDelete('cascade');
            
            // Answer content
            $table->text('answer_text');  // Option text (e.g., "Laravel is a PHP framework")
            
            // Correctness
            $table->boolean('is_correct')->default(false);  // TRUE if this is the right answer
            
            // For MCQ: multiple options, only one is correct
            // For TF: 2 options, one is correct
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};