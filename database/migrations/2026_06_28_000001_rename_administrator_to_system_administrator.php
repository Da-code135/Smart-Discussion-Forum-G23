<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename existing 'Administrator' role to 'System Administrator'
        DB::table('roles')
            ->where('role_name', 'Administrator')
            ->update([
                'role_name' => 'System Administrator',
                'description' => 'Full system-wide access to all features, user management, role assignment, and system configuration. Can manage all users and groups.'
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')
            ->where('role_name', 'System Administrator')
            ->update([
                'role_name' => 'Administrator',
                'description' => 'Full access to all features including moderation, statistics, user management, and system configuration.'
            ]);
    }
};
