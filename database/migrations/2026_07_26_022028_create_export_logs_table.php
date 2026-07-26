<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the export_logs table (SDD §4.2 "Export Logs").
     * Each row records that a user exported a topic thread to a file.
     */
    public function up(): void
    {
        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();

            // Which topic was exported
            $table->foreignId('topic_id')
                ->constrained('topics')
                ->onDelete('cascade');

            // Who performed the export
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Export format (currently only PDF is supported)
            $table->string('file_type', 10)->default('pdf');

            $table->timestamps();

            // Index for per-topic and per-user export history
            $table->index('topic_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_logs');
    }
};
