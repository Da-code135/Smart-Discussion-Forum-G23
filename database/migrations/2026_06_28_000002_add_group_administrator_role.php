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
        // Add Group Administrator role
        DB::table('roles')
            ->insert([
                'role_name' => 'Group Administrator',
                'description' => 'Can manage assigned groups, group members, and group-specific content. Limited to groups they are assigned to administer.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')
            ->where('role_name', 'Group Administrator')
            ->delete();
    }
};
