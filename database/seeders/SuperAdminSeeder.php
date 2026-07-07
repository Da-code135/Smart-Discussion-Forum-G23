<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates a System Administrator user for development testing.
     */
    public function run(): void
    {
        // Get or create System Administrator role
        $adminRole = Role::firstOrCreate(
            ['role_name' => 'System Administrator'],
            ['description' => 'Full system-wide access to all features, user management, role assignment, and system configuration.']
        );

        // Create super admin user — group_agnostic (no group_id)
        // System Administrators do not need a group; they can access all groups.
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'full_name' => 'Super Admin',
                'password' => bcrypt('password'),
                'role_id' => $adminRole->id,
                'group_id' => null,
                'account_status' => 'active',
            ]
        );

        $this->command->info('✅ Super Admin user created/updated successfully!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  Email:    superadmin@example.com');
        $this->command->info('  Password: password');
        $this->command->info("  Role:     {$adminRole->role_name}");
        $this->command->info("  User ID:  {$superAdmin->id}");
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
