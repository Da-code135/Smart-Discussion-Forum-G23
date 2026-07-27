<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tables that the seeders write to and must not grow on re-runs.
     *
     * @var list<string>
     */
    private const TRACKED_TABLES = [
        'roles',
        'groups',
        'users',
        'topic_categories',
        'quizzes',
        'quiz_configuration',
        'questions',
        'answers',
        'topics',
        'posts',
        'reports',
        'moderation_logs',
        'warnings',
        'blacklist_records',
        'student_attempts',
        'student_answers',
        'grades',
        'audit_logs',
        'notifications',
        'recommendation_log',
        'statistics',
    ];

    public function test_database_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $firstRunCounts = $this->tableCounts();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($firstRunCounts, $this->tableCounts());
        $this->assertSame(3, DB::table('groups')->count());
        $this->assertSame(1, DB::table('quizzes')->where('title', 'Laravel Basics Quiz')->count());
        $this->assertSame(1, DB::table('quizzes')->where('title', 'PHP Fundamentals Quiz')->count());
        $this->assertSame(1, DB::table('topics')->where('title', 'What is the derivative of x²?')->count());
    }

    public function test_demo_data_seeder_is_idempotent_on_its_own(): void
    {
        $this->seed(DatabaseSeeder::class);
        $firstRunCounts = $this->tableCounts();

        $this->seed(DemoDataSeeder::class);

        $this->assertSame($firstRunCounts, $this->tableCounts());
        $this->assertSame(1, DB::table('users')->where('email', 'lecturer@example.com')->count());
        $this->assertSame(3, DB::table('statistics')->count());
    }

    /**
     * Snapshot the row count of every tracked table.
     *
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        $counts = [];

        foreach (self::TRACKED_TABLES as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
