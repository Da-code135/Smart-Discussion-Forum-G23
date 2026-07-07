<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the recommendation_log table.
     *
     * Each row records: "The system recommended topic X to user Y on date Z
     * because of reason W." The unique constraint on (user_id, topic_id)
     * prevents the same topic from being recommended to the same user twice.
     */
    public function up(): void
    {
        Schema::create('recommendation_log', function (Blueprint $table) {
            $table->id();

            // Who received the recommendation
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Which topic was recommended
            $table->foreignId('topic_id')
                ->constrained('topics')
                ->onDelete('cascade');

            // Which group the topic belongs to (denormalised for query speed)
            $table->foreignId('group_id')
                ->constrained('groups')
                ->onDelete('cascade');

            // When the recommendation was served
            $table->timestamp('recommended_at');

            // Why this topic was recommended
            // e.g. "Based on your reading history", "Popular in your group", "Similar to topics you engaged with"
            $table->string('reason', 255)->nullable();

            $table->timestamps();

            // Never recommend the same topic to the same user twice
            $table->unique(['user_id', 'topic_id'], 'uq_recommendation_user_topic');

            // Index for fetching recommendations for a specific user
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_log');
    }
};
