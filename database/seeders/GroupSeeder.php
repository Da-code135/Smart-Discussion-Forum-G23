<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;

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
