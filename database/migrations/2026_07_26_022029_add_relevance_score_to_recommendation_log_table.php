<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds relevance_score to the recommendation log (SDD §4.2 / Table 7):
     * a 0-100 percentage indicating how strongly the topic matches the
     * user's engagement profile.
     */
    public function up(): void
    {
        Schema::table('recommendation_log', function (Blueprint $table) {
            $table->unsignedTinyInteger('relevance_score')
                ->nullable()
                ->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recommendation_log', function (Blueprint $table) {
            $table->dropColumn('relevance_score');
        });
    }
};
