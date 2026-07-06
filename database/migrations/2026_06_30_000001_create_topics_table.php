<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the topics table — the core of the Discussion Forum.
     * Each topic is a discussion thread owned by a group and created by a user.
     */
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();

            // Which group owns this topic — critical for group isolation
            $table->foreignId('group_id')
                ->constrained('groups')
                ->onDelete('cascade');

            // Who started this topic
            $table->foreignId('created_by')
                ->constrained('users')
                ->onDelete('cascade');

            // Discussion title — must be unique to prevent duplicate threads
            $table->string('title', 255)->unique();

            // Fuller context / body of the discussion starter
            $table->text('description');

            // Is this topic still open for replies?
            $table->enum('status', ['active', 'archived'])->default('active');

            // ML classification hint: is this a discussion or a question?
            $table->enum('post_type', ['discussion', 'question'])->default('discussion');

            $table->timestamps();

            // Indexes for performance
            $table->index('group_id');
            $table->index('created_by');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
