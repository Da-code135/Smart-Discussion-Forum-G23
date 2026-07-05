<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the post_visibility table — stores exclusion rules.
     * Default behaviour: everyone in the group can see every post.
     * This table only has rows for EXCEPTIONS (users excluded from specific posts).
     * It is a sparse table: most posts have no rows here.
     *
     * Depends on: posts, users (both must exist first).
     */
    public function up(): void
    {
        Schema::create('post_visibility', function (Blueprint $table) {
            $table->id();

            // Which post has a visibility restriction
            $table->foreignId('post_id')
                  ->constrained('posts')
                  ->onDelete('cascade');

            // Which user is excluded from seeing this post
            $table->foreignId('excluded_user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->timestamp('created_at')->useCurrent();

            // Prevent duplicate exclusion rules
            $table->unique(['post_id', 'excluded_user_id'], 'uq_post_visibility');

            // Indexes for performance
            $table->index('post_id');
            $table->index('excluded_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_visibility');
    }
};
