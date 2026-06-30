<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: Role names are now managed by RoleSeeder.
     * This migration is kept for historical compatibility but performs no action.
     */
    public function up(): void
    {
        // Role names are now managed by RoleSeeder
        // This migration is kept for backward compatibility
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed - roles are managed by RoleSeeder
    }
};
