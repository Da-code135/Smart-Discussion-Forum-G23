<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id('quiz_id');

            // Foreign key to lecturer (user who created it)
            $table->foreignId('lecturer_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Quiz metadata
            $table->string('title', 255);  // Quiz name (e.g., "Midterm - Laravel Basics")
            $table->text('description')->nullable();  // Optional description

            // Target audience
            $table->string('target_category', 100);  // E.g., "BSSE Year 3" or "Student" role

            // Scheduling
            $table->date('scheduled_date');  // Date quiz is scheduled
            $table->time('start_time');  // Time quiz goes live (e.g., 10:00)
            $table->integer('duration_minutes');  // How long quiz lasts (e.g., 60)

            // Status
            $table->boolean('is_active')->default(false);  // TRUE when quiz is currently live
            $table->timestamp('published_at')->nullable();  // When quiz was published as announcement

            $table->timestamps();  // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
