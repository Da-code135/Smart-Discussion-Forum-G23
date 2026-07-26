<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Seed demo accounts for every role.
     *
     * Every user is keyed by email via firstOrCreate, so re-running this
     * seeder never duplicates accounts. Run AFTER the core seeders
     * (RoleSeeder, GroupSeeder, SuperAdminSeeder).
     */
    public function run(): void
    {
        $groupAdminRole = Role::where('role_name', 'Group Administrator')->firstOrFail();
        $studentRole = Role::where('role_name', 'Student')->firstOrFail();
        $lecturerRole = Role::where('role_name', 'Lecturer')->firstOrFail();
        $memberRole = Role::where('role_name', 'Member')->firstOrFail();

        $lecturerGroup = Group::where('group_type', 'lecturer')->firstOrFail();
        $studentGroup = Group::where('group_type', 'student')->firstOrFail();

        $superAdmin = User::where('email', 'superadmin@example.com')->firstOrFail();

        $groupAdmin = User::firstOrCreate(
            ['email' => 'groupadmin@example.com'],
            [
                'full_name' => 'Alice Group Manager',
                'password' => bcrypt('password'),
                'role_id' => $groupAdminRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'last_active_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'student1@example.com'],
            [
                'full_name' => 'Bob Student',
                'password' => bcrypt('password'),
                'role_id' => $studentRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'last_active_at' => now()->subDay(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'student2@example.com'],
            [
                'full_name' => 'Carol Student',
                'password' => bcrypt('password'),
                'role_id' => $studentRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'last_active_at' => now()->subDays(3),
            ]
        );

        User::firstOrCreate(
            ['email' => 'student3@example.com'],
            [
                'full_name' => 'Inactive Student',
                'password' => bcrypt('password'),
                'role_id' => $studentRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'last_active_at' => now()->subDays(45), // Inactive 45 days
            ]
        );

        $lecturer = User::firstOrCreate(
            ['email' => 'lecturer@example.com'],
            [
                'full_name' => 'Dr. David Lecturer',
                'password' => bcrypt('password'),
                'role_id' => $lecturerRole->id,
                'group_id' => $lecturerGroup->id,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'last_active_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'full_name' => 'Eve Member',
                'password' => bcrypt('password'),
                'role_id' => $memberRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'last_active_at' => now()->subWeek(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'blacklisted@example.com'],
            [
                'full_name' => 'Bad Actor User',
                'password' => bcrypt('password'),
                'role_id' => $memberRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'blacklisted',
                'blacklisted_at' => now(),
            ]
        );

        // Access assignments — addAdmin() is guarded by hasAdmin() and
        // syncWithoutDetaching() never duplicates pivot rows.
        $studentGroup->addAdmin($groupAdmin, $superAdmin->id);
        $lecturer->taughtGroups()->syncWithoutDetaching([$studentGroup->id]);
        $superAdmin->taughtGroups()->syncWithoutDetaching([
            $studentGroup->id,
            $lecturerGroup->id,
        ]);

        $this->command->info('✅ Demo users ready (groupadmin, student1-3, lecturer, member, blacklisted).');
    }
}
