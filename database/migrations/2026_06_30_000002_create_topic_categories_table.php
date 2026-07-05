<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the topic_categories table for ML classification.
     * Each category belongs to a group and includes keyword hints
     * that the classifier uses to auto-tag posts.
     *
     * This migration runs BEFORE posts because posts reference categories.
     */
    public function up(): void
    {
        Schema::create('topic_categories', function (Blueprint $table) {
            $table->id();

            // Categories are per-group (each group can have its own categories)
            $table->foreignId('group_id')
                  ->constrained('groups')
                  ->onDelete('cascade');

            // e.g., 'Mathematics', 'Programming', 'Science', 'General'
            $table->string('category_name', 100);

            // Composite unique: same category name can exist in different groups
            $table->unique(['group_id', 'category_name'], 'uq_topic_categories_group_name');

            // Comma-separated keywords for ML classifier matching
            $table->text('keyword_hints')->nullable();

            $table->timestamps();

            // Index for group-scoped queries
            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topic_categories');
    }
};
