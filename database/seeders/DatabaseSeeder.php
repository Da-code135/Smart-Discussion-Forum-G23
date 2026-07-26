<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Core seeders run first (required reference data), then the demo
     * seeders layer sample content on top. Every seeder is idempotent,
     * so re-running db:seed never duplicates rows.
     */
    public function run(): void
    {
        // Core data — required for the application to work
        $this->call([
            RoleSeeder::class,
            GroupSeeder::class,
            SuperAdminSeeder::class,
            TopicCategorySeeder::class,
        ]);

        // Demo data — sample accounts and content for testing/presentation
        $this->call(DemoDataSeeder::class);
    }
}
