<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'role_name' => 'System Administrator',
                'description' => 'Full system-wide access to all features, user management, role assignment, and system configuration. Can manage all users and groups.',
            ],
            [
                'id' => 2,
                'role_name' => 'Group Administrator',
                'description' => 'Can manage assigned groups, group members, and group-specific content. Limited to groups they are assigned to administer.',
            ],
            [
                'id' => 3,
                'role_name' => 'Student',
                'description' => 'Access to quiz attempts, discussion features, and performance reports.',
            ],
            [
                'id' => 4,
                'role_name' => 'Lecturer',
                'description' => 'Access to quiz configuration, participation marking criteria, and discussion features.',
            ],
            [
                'id' => 5,
                'role_name' => 'Member',
                'description' => 'Access to discussion features, topic filtering, PDF export, and social media forwarding only.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrInsert(
                ['id' => $role['id']],
                [
                    'role_name' => $role['role_name'],
                    'description' => $role['description'],
                ]
            );
        }
    }
}
