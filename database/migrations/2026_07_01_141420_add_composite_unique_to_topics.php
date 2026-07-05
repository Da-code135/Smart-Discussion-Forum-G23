<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropUnique(['title']);//this drops the old global unique constraint
            $table->unique(['group_id', 'title']);//this adds a new composite unique constraint
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
          // Revert: drop composite unique
            $table->dropUnique(['group_id', 'title']);
            
            // Revert: add back old global unique
            $table->unique(['title']);
        });
    }
};
