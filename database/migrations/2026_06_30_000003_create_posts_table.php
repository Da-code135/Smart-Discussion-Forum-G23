<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the posts table — each row is an individual reply within a topic.
     * Posts ARE the replies (there is no separate replies table).
     *
     * Depends on: topics, users, topic_categories (all must exist first).
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            // Which topic this reply belongs to
            $table->foreignId('topic_id')
                  ->constrained('topics')
                  ->onDelete('cascade');

            // Who wrote this reply
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // The reply content — LONGTEXT supports long-form responses
            $table->longText('content');

            // Soft-delete flag: set to TRUE when moderated
            // Keeps the record for audit trails; hides from regular users
            $table->boolean('is_removed')->default(false);

            // ML category assigned by ClassifyPostJob (nullable until classified)
            $table->foreignId('category_id')
                  ->nullable()
                  ->constrained('topic_categories')
                  ->onDelete('set null');

            $table->timestamps();

            // Indexes for performance
            $table->index('topic_id');
            $table->index('user_id');
            $table->index('is_removed');
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
