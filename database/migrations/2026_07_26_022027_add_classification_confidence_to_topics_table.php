<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds ML classification metadata to topics (SDD §5.2.2 / Appendix A):
     * - classification_confidence: 0-100 score of how confident the
     *   keyword classifier was in the assigned category.
     * - classification_needs_review: flagged when confidence falls below
     *   the configured threshold so an administrator can review it.
     */
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->unsignedTinyInteger('classification_confidence')
                ->nullable()
                ->after('category_id');
            $table->boolean('classification_needs_review')
                ->default(false)
                ->after('classification_confidence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn([
                'classification_confidence',
                'classification_needs_review',
            ]);
        });
    }
};
