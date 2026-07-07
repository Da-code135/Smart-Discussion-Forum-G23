<?php

namespace Tests\Feature\Console;

use App\Models\BlacklistRecord;
use App\Models\SystemConfig;
use App\Models\User;
use App\Models\Warning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class MonitorMemberActivityTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
        Cache::flush();
    }

    // ============================================
    // WARNING 1: Inactive user gets first warning
    // ============================================

    public function test_inactive_user_receives_warning_1(): void
    {
        SystemConfig::updateOrCreate(
            ['config_key' => 'inactivity_warning_days'],
            ['config_value' => '30']
        );
        SystemConfig::updateOrCreate(
            ['config_key' => 'warning_response_days'],
            ['config_value' => '7']
        );
        Cache::flush();

        $user = $this->createStudent([
            'last_active_at' => now()->subDays(31),
        ]);

        // Verify data exists before command
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('system_configs', ['config_key' => 'inactivity_warning_days']);

        $this->artisan('monitor:activity')
            ->assertSuccessful();

        $warning = Warning::where('user_id', $user->id)->first();
        $this->assertNotNull($warning);
        $this->assertEquals(1, $warning->warning_number);
        $this->assertFalse($warning->is_acknowledged);

        $user->refresh();
        $this->assertEquals('warned', $user->account_status);
    }

    public function test_active_user_does_not_receive_warning(): void
    {
        SystemConfig::updateOrCreate(
            ['config_key' => 'inactivity_warning_days'],
            ['config_value' => 30]
        );

        $user = $this->createStudent([
            'last_active_at' => now()->subDays(5),
        ]);

        $this->artisan('monitor:activity')
            ->assertSuccessful();

        $warning = Warning::where('user_id', $user->id)->first();
        $this->assertNull($warning);

        $user->refresh();
        $this->assertEquals('active', $user->account_status);
    }

    // ============================================
    // WARNING 2: Warning 1 expired → Warning 2
    // ============================================

    public function test_expired_warning_1_leads_to_warning_2(): void
    {
        SystemConfig::updateOrCreate(
            ['config_key' => 'inactivity_warning_days'],
            ['config_value' => 30]
        );
        SystemConfig::updateOrCreate(
            ['config_key' => 'warning_response_days'],
            ['config_value' => 7]
        );

        $user = $this->createStudent([
            'account_status' => 'warned',
            'last_active_at' => now()->subDays(60),
        ]);

        // Create Warning 1 with expired deadline
        Warning::create([
            'user_id' => $user->id,
            'warning_number' => 1,
            'reason' => 'Inactivity',
            'response_deadline' => now()->subDay(),
            'is_acknowledged' => false,
            'is_resolved' => false,
        ]);

        $this->artisan('monitor:activity')
            ->assertSuccessful();

        $warning2 = Warning::where('user_id', $user->id)
            ->where('warning_number', 2)
            ->first();
        $this->assertNotNull($warning2);
        $this->assertFalse($warning2->is_acknowledged);
    }

    public function test_active_warning_1_does_not_lead_to_warning_2(): void
    {
        SystemConfig::updateOrCreate(
            ['config_key' => 'inactivity_warning_days'],
            ['config_value' => 30]
        );

        $user = $this->createStudent([
            'account_status' => 'warned',
            'last_active_at' => now()->subDays(60),
        ]);

        // Create Warning 1 with future deadline (not expired)
        Warning::create([
            'user_id' => $user->id,
            'warning_number' => 1,
            'reason' => 'Inactivity',
            'response_deadline' => now()->addDays(5),
            'is_acknowledged' => false,
            'is_resolved' => false,
        ]);

        $this->artisan('monitor:activity')
            ->assertSuccessful();

        // No Warning 2 should be created
        $warning2 = Warning::where('user_id', $user->id)
            ->where('warning_number', 2)
            ->first();
        $this->assertNull($warning2);
    }

    // ============================================
    // BLACKLIST: Warning 2 expired → Blacklist
    // ============================================

    public function test_expired_warning_2_leads_to_blacklist(): void
    {
        SystemConfig::updateOrCreate(
            ['config_key' => 'inactivity_warning_days'],
            ['config_value' => 30]
        );
        SystemConfig::updateOrCreate(
            ['config_key' => 'warning_response_days'],
            ['config_value' => 7]
        );
        SystemConfig::updateOrCreate(
            ['config_key' => 'blacklist_duration_days'],
            ['config_value' => 90]
        );

        $user = $this->createStudent([
            'account_status' => 'warned',
            'last_active_at' => now()->subDays(90),
        ]);

        // Create Warning 2 with expired deadline
        Warning::create([
            'user_id' => $user->id,
            'warning_number' => 2,
            'reason' => 'Inactivity - Failed to respond to Warning 1',
            'response_deadline' => now()->subDay(),
            'is_acknowledged' => false,
            'is_resolved' => false,
        ]);

        $this->artisan('monitor:activity')
            ->assertSuccessful();

        $user->refresh();
        $this->assertEquals('blacklisted', $user->account_status);

        $blacklist = BlacklistRecord::where('user_id', $user->id)->first();
        $this->assertNotNull($blacklist);
        $this->assertNull($blacklist->lifted_at);
    }

    public function test_active_warning_2_does_not_lead_to_blacklist(): void
    {
        SystemConfig::updateOrCreate(
            ['config_key' => 'inactivity_warning_days'],
            ['config_value' => 30]
        );

        $user = $this->createStudent([
            'account_status' => 'warned',
            'last_active_at' => now()->subDays(90),
        ]);

        // Warning 2 with future deadline
        Warning::create([
            'user_id' => $user->id,
            'warning_number' => 2,
            'reason' => 'Inactivity',
            'response_deadline' => now()->addDays(5),
            'is_acknowledged' => false,
            'is_resolved' => false,
        ]);

        $this->artisan('monitor:activity')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotEquals('blacklisted', $user->account_status);
    }

    // ============================================
    // DRY RUN MODE
    // ============================================

    public function test_dry_run_does_not_make_changes(): void
    {
        SystemConfig::updateOrCreate(
            ['config_key' => 'inactivity_warning_days'],
            ['config_value' => 30]
        );

        $user = $this->createStudent([
            'last_active_at' => now()->subDays(31),
        ]);

        $this->artisan('monitor:activity --dry-run')
            ->assertSuccessful();

        // No warning should be created
        $warning = Warning::where('user_id', $user->id)->first();
        $this->assertNull($warning);

        $user->refresh();
        $this->assertEquals('active', $user->account_status);
    }

    // ============================================
    // NO DUPLICATE WARNINGS
    // ============================================

    public function test_no_duplicate_warnings_for_same_user(): void
    {
        SystemConfig::updateOrCreate(
            ['config_key' => 'inactivity_warning_days'],
            ['config_value' => 30]
        );
        SystemConfig::updateOrCreate(
            ['config_key' => 'warning_response_days'],
            ['config_value' => 7]
        );

        $user = $this->createStudent([
            'last_active_at' => now()->subDays(31),
        ]);

        // Run twice
        $this->artisan('monitor:activity')->assertSuccessful();
        $this->artisan('monitor:activity')->assertSuccessful();

        // Should have exactly 1 warning (not 2)
        $warnings = Warning::where('user_id', $user->id)->get();
        $this->assertEquals(1, $warnings->count());
    }
}
