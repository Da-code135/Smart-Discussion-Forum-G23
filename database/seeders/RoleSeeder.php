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
        Role::insert([
            ['role_name' => 'System Administrator', 'description' => 'Full access to all features including moderation, statistics, user management, and system configuration.'],
            ['role_name' => 'Group Administrator', 'description' => 'Access to quiz configuration, participation marking criteria, and discussion features.'],
            ['role_name' => 'Student', 'description' => 'Access to quiz attempts, discussion features, and performance reports.'],
            ['role_name' => 'Member', 'description' => 'Access to discussion features, topic filtering, PDF export, and social media forwarding only.'],
        ]);
    }
}
