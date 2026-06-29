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
        Group::insert([
            [
                'group_name' => 'General',
                'description' => 'Default group for all new users',
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group_name' => 'Default Group',
                'description' => 'Default test group',
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
