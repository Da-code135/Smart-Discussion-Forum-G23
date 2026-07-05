<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Group;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Group::create([
            'group_name' => 'Platform Administrators',
            'group_type' => 'sysadmin',
        ]);

        Group::create([
            'group_name' => 'Faculty',
            'group_type' => 'lecturer',
        ]);

        Group::create([
            'group_name' => 'Students',
            'group_type' => 'student',
        ]);
    }
}
