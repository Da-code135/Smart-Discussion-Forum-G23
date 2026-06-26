<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        \App\Models\Group::create([
        'group_name' => 'Default Group',
        'description' => 'Default test group',
    ]);

        // User::factory(10)->create();

        User::factory()->create([
            'full_name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        

    }
}
