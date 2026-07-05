<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_configuration', function (Blueprint $table) {
            $table->id('config_id');
            
            // One config per quiz
            $table->foreignId('quiz_id')
                  ->unique()  // Each quiz has exactly ONE config
                  ->constrained('quizzes', 'quiz_id')
                  ->onDelete('cascade');
            
            // Settings
            $table->boolean('allow_late_join')->default(false);  
            // If FALSE: late joiners can't even start quiz
            // If TRUE: late joiners can start but get no extra time
            
            $table->integer('notification_minutes_before')->default(15);  
            // Send reminder X minutes before quiz starts
            
            $table->text('participation_criteria')->nullable();  
            // How lecturer awards participation marks
            // E.g., "Full marks if attempted, half if score < 50%"
            
            $table->boolean('lock_screen_on_start')->default(true);  
            // If TRUE: quiz locks interface, can't navigate away
            
            $table->boolean('show_results_after_close')->default(true);  
            // If TRUE: show performance report to all after quiz closes
            
            $table->boolean('show_correct_answers')->default(false);  
            // If TRUE: show which answers were correct after close
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_configuration');
    }
};