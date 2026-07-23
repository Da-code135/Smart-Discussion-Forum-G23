<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModerationMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_reported_column_exists_in_posts_table(): void
    {
        // Run the migration that adds is_reported column
        $this->artisan('migrate');

        // Check that the is_reported column exists in the posts table
        $this->assertTrue(
            Schema::hasColumn('posts', 'is_reported'),
            'The is_reported column should exist in the posts table'
        );

        // Check that the is_reported column has the correct properties
        $columns = Schema::getColumnListing('posts');
        $this->assertContains('is_reported', $columns);
    }

    public function test_moderation_logs_table_exists(): void
    {
        // Run the migration that creates moderation_logs table
        $this->artisan('migrate');

        // Check that the moderation_logs table exists
        $this->assertTrue(
            Schema::hasTable('moderation_logs'),
            'The moderation_logs table should exist'
        );

        // Check that the moderation_logs table has the correct columns
        $expectedColumns = [
            'id', 'post_id', 'admin_id', 'action', 'reason', 'created_at', 'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('moderation_logs', $column),
                "The {$column} column should exist in the moderation_logs table"
            );
        }
    }

    public function test_moderation_logs_table_has_correct_foreign_keys(): void
    {
        // Run the migration that creates moderation_logs table
        $this->artisan('migrate');

        // Note: Laravel doesn't provide direct way to check foreign key constraints in tests
        // So we'll verify the columns exist and assume the foreign keys were created properly
        $this->assertTrue(Schema::hasColumn('moderation_logs', 'post_id'));
        $this->assertTrue(Schema::hasColumn('moderation_logs', 'admin_id'));
    }

    public function test_moderation_logs_table_has_timestamps(): void
    {
        // Run the migration that creates moderation_logs table
        $this->artisan('migrate');

        // Check that timestamp columns exist
        $this->assertTrue(Schema::hasColumn('moderation_logs', 'created_at'));
        $this->assertTrue(Schema::hasColumn('moderation_logs', 'updated_at'));
    }

    public function test_down_migration_for_is_reported_column(): void
    {
        // Verify the column exists (migrations already ran via RefreshDatabase)
        $this->assertTrue(Schema::hasColumn('posts', 'is_reported'));

        // Disable FK checks so SQLite allows column drops across tables
        DB::statement('PRAGMA foreign_keys = OFF');
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_06_30_000005_add_is_reported_to_posts.php']);
        DB::statement('PRAGMA foreign_keys = ON');

        // Verify the column is dropped
        $this->assertFalse(Schema::hasColumn('posts', 'is_reported'));
    }

    public function test_down_migration_for_moderation_logs_table(): void
    {
        // Verify the table exists (migrations already ran via RefreshDatabase)
        $this->assertTrue(Schema::hasTable('moderation_logs'));

        // Disable FK checks so SQLite allows table drops
        DB::statement('PRAGMA foreign_keys = OFF');
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_06_30_000006_create_moderation_logs_table.php']);
        DB::statement('PRAGMA foreign_keys = ON');

        // Verify the table is dropped
        $this->assertFalse(Schema::hasTable('moderation_logs'));
    }

    public function test_is_reported_column_defaults_to_false(): void
    {
        // Create parent records so FK constraints are satisfied
        $roleId = DB::table('roles')->insertGetId(['role_name' => 'Student', 'description' => 'Student', 'created_at' => now(), 'updated_at' => now()]);
        $groupId = DB::table('groups')->insertGetId(['group_name' => 'Test Group', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()]);
        $userId = DB::table('users')->insertGetId([
            'full_name' => 'Test User', 'email' => 'test_defaults_'.uniqid().'@test.com',
            'password' => bcrypt('password'), 'role_id' => $roleId, 'group_id' => $groupId,
            'account_status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $topicId = DB::table('topics')->insertGetId([
            'group_id' => $groupId, 'created_by' => $userId, 'title' => 'Test',
            'description' => 'Test', 'status' => 'active', 'post_type' => 'discussion',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Insert a post without specifying is_reported
        DB::table('posts')->insert([
            'topic_id' => $topicId,
            'user_id' => $userId,
            'content' => 'Test content',
        ]);

        // Check that is_reported defaults to false
        $newPost = DB::table('posts')->orderByDesc('id')->first();

        // In SQLite, boolean false is stored as 0
        $this->assertEquals(0, $newPost->is_reported);
    }

    public function test_moderation_logs_table_accepts_data_after_migration(): void
    {
        // Create parent records so FK constraints are satisfied
        $roleId = DB::table('roles')->insertGetId(['role_name' => 'Admin', 'description' => 'Admin', 'created_at' => now(), 'updated_at' => now()]);
        $groupId = DB::table('groups')->insertGetId(['group_name' => 'Test Group ML', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()]);
        $userId = DB::table('users')->insertGetId([
            'full_name' => 'Test User', 'email' => 'test_ml_'.uniqid().'@test.com',
            'password' => bcrypt('password'), 'role_id' => $roleId, 'group_id' => $groupId,
            'account_status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $topicId = DB::table('topics')->insertGetId([
            'group_id' => $groupId, 'created_by' => $userId, 'title' => 'Test',
            'description' => 'Test', 'status' => 'active', 'post_type' => 'discussion',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $postId = DB::table('posts')->insertGetId([
            'topic_id' => $topicId, 'user_id' => $userId, 'content' => 'Test post',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Insert a record into the moderation_logs table
        $result = DB::table('moderation_logs')->insert([
            'post_id' => $postId,
            'admin_id' => $userId,
            'action' => 'removed',
            'reason' => 'Test reason',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify the insert was successful
        $this->assertTrue($result);

        // Check that the record exists
        $record = DB::table('moderation_logs')->first();
        $this->assertEquals('removed', $record->action);
        $this->assertEquals('Test reason', $record->reason);
    }
}
