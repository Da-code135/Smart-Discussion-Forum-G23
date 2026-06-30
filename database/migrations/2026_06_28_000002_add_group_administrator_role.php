<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: Group Administrator role is now seeded by RoleSeeder.
     * This migration is kept for historical compatibility but performs no action.
     */
    public function up(): void
    {
        // Group Administrator role is now managed by RoleSeeder
        // This migration is kept for backward compatibility
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed - role is managed by RoleSeeder
    }
};
