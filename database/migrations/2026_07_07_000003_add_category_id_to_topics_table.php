<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a category_id foreign key to the topics table so topics can be
     * classified into categories (for recommendation & filtering).
     *
     * This is separate from the post-level category_id already on the posts
     * table; a topic can have its own overall category (e.g. "Django") while
     * individual replies within it may be further classified.
     *
     * Uses SET NULL so deleting a category does not cascade-delete topics.
     */
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('post_type')
                ->constrained('topic_categories')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
