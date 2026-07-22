<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('group_id');
            $table->index('role_id');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('warnings', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('blacklist_records', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->index('quiz_id');
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->index('question_id');
        });

        Schema::table('student_attempts', function (Blueprint $table) {
            $table->index('quiz_id');
            $table->index('student_id');
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->index('quiz_id');
            $table->index('student_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
            $table->dropIndex(['role_id']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('warnings', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('blacklist_records', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['quiz_id']);
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->dropIndex(['question_id']);
        });

        Schema::table('student_attempts', function (Blueprint $table) {
            $table->dropIndex(['quiz_id']);
            $table->dropIndex(['student_id']);
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->dropIndex(['quiz_id']);
            $table->dropIndex(['student_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
        });
    }
};
