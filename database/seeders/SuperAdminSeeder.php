<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Group;

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

        // Get the General group (created by GroupSeeder)
        $generalGroup = Group::where('group_name', 'General')->firstOrFail();

        // Create super admin user
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'full_name' => 'Super Admin',
                'password' => bcrypt('password'),
                'role_id' => $adminRole->id,
                'group_id' => $generalGroup->id,
                'account_status' => 'active',
            ]
        );

        $this->command->info('✅ Super Admin user created/updated successfully!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info("  Email:    admin@admin.com");
        $this->command->info("  Password: password");
        $this->command->info("  Role:     {$adminRole->role_name}");
        $this->command->info("  User ID:  {$superAdmin->id}");
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
