<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed demo data for every application feature.
     *
     * Thin orchestrator — the actual demo content lives in dedicated,
     * fully idempotent seeders. Run AFTER the core seeders (RoleSeeder,
     * GroupSeeder, SuperAdminSeeder, TopicCategorySeeder); DatabaseSeeder
     * already calls everything in the right order.
     */
    public function run(): void
    {
        $this->call([
            DemoUserSeeder::class,
            DemoForumSeeder::class,
            DemoQuizSeeder::class,
            DemoActivitySeeder::class,
        ]);

        $this->command->info('═══════════════════════════════════════════════');
        $this->command->info('  ✅ Demo data seeded (idempotent — safe to re-run).');
        $this->command->info('═══════════════════════════════════════════════');
        $this->command->info('  Test accounts (password: password):');
        $this->command->info('    superadmin@example.com  — System Admin');
        $this->command->info('    groupadmin@example.com  — Group Admin');
        $this->command->info('    student1@example.com    — Student');
        $this->command->info('    student2@example.com    — Student');
        $this->command->info('    student3@example.com    — Student (inactive, warned)');
        $this->command->info('    lecturer@example.com    — Lecturer');
        $this->command->info('    member@example.com      — Member');
        $this->command->info('    blacklisted@example.com — Blacklisted');
        $this->command->info('═══════════════════════════════════════════════');
    }
}
