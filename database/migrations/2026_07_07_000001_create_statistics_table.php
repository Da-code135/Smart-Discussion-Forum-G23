<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the statistics table — one row per group, storing a snapshot of
     * key engagement metrics. Updated periodically (or manually via the
     * "Recalculate" button on the admin dashboard).
     *
     * The unique constraint on group_id ensures there is exactly one stats
     * row per group — no duplicates.
     */
    public function up(): void
    {
        Schema::create('statistics', function (Blueprint $table) {
            $table->id();

            // Each group has exactly one statistics row
            $table->foreignId('group_id')
                ->constrained('groups')
                ->onDelete('cascade');

            // Membership metrics
            $table->integer('total_members')->default(0);             // How many users in the group?
            $table->integer('active_members_this_week')->default(0);   // Users who posted or were active this week

            // Content metrics
            $table->integer('total_topics')->default(0);              // Total topics ever created in this group
            $table->integer('total_posts')->default(0);               // Total replies ever in this group

            // Engagement flags
            $table->integer('unanswered_questions')->default(0);      // Topics of type 'question' with 0 replies
            $table->integer('inactive_members_30days')->default(0);   // Users who haven't posted in 30+ days

            // When this snapshot was last recomputed
            $table->timestamp('last_calculated_at')->nullable();

            $table->timestamps();

            // One stats row per group — enforced at the database level
            $table->unique('group_id', 'uq_statistics_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statistics');
    }
};
