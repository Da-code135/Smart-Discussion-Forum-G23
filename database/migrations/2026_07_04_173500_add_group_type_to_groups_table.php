<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds group_type column to the groups table.
     * The original migration (2026_06_23_203519) was updated after being run,
     * so the column wasn't created in existing databases.
     *
     * Types: sysadmin, lecturer, student
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->enum('group_type', ['sysadmin', 'lecturer', 'student'])
                ->default('student')
                ->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('group_type');
        });
    }
};
