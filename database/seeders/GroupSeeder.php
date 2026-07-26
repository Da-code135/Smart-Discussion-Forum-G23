<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Uses firstOrCreate keyed by group_type so re-running the seeder
     * (e.g. on every container boot) never duplicates the base groups.
     */
    public function run(): void
    {
        Group::firstOrCreate(
            ['group_type' => 'sysadmin'],
            ['group_name' => 'Platform Administrators'],
        );

        Group::firstOrCreate(
            ['group_type' => 'lecturer'],
            ['group_name' => 'Faculty'],
        );

        Group::firstOrCreate(
            ['group_type' => 'student'],
            ['group_name' => 'Students'],
        );
    }
}
