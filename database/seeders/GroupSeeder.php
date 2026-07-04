<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Seeder;
use App\Models\Group;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Truncate existing groups
        DB::table('groups')->truncate();
        
        // Create sysadmin group
        Group::create([
            'group_name' => 'Platform Administrators', 
            'group_type' => 'sysadmin'
        ]);
        
        // Create lecturer group
        Group::create([
            'group_name' => 'Faculty', 
            'group_type' => 'lecturer'
        ]);
    }
}