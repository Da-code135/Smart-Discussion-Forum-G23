<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger of engagement activities that earn participation points.
     *
     * One row per awarded activity. Deduplication (one login per day,
     * one award per quiz) is enforced by ParticipationService.
     */
    public function up(): void
    {
        Schema::create('participation_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('activity_type', 50); // daily_login | topic_created | reply_posted | quiz_completed
            $table->decimal('points', 8, 2);
            $table->string('subject_type')->nullable(); // model class of the related record
            $table->unsignedBigInteger('subject_id')->nullable(); // topic/post/quiz id
            $table->date('activity_date'); // calendar day, used for daily dedupe & time-based reports
            $table->timestamps();

            $table->index(['user_id', 'activity_type']);
            $table->index(['user_id', 'activity_type', 'activity_date']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_activities');
    }
};
