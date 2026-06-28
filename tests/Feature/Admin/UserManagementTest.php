<?php

namespace Tests\Feature\Admin;

use App\Models\BlacklistRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase, CreatesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    // ============================================
    // USER LIST
    // ============================================

    public function test_admin_can_view_users_list(): void
    {
        $admin = $this->createSystemAdmin();
        $this->createStudent();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));
        $response->assertStatus(200);
        $response->assertViewIs('admin.users.index');
        $response->assertViewHas('users');
    }

    public function test_admin_can_search_users(): void
    {
        $admin = $this->createSystemAdmin();
        $this->createStudent(['full_name' => 'John Smith', 'email' => 'john@test.com']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'John']));
        $response->assertStatus(200);
    }

    public function test_admin_can_filter_users_by_status(): void
    {
        $admin = $this->createSystemAdmin();
        $this->createStudent(['account_status' => 'active']);
        $this->createMember(['account_status' => 'warned', 'email' => 'warned@test.com']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['account_status' => 'warned']));
        $response->assertStatus(200);
    }

    // ============================================
    // LIFT BLACKLIST
    // ============================================

    public function test_admin_can_lift_blacklist(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent(['account_status' => 'blacklisted']);

        BlacklistRecord::create([
            'user_id' => $user->id,
            'reason' => 'Test',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.lift-blacklist', $user));

        $response->assertRedirect();
        $user->refresh();
        $this->assertEquals('active', $user->account_status);

        $blacklistRecord = BlacklistRecord::where('user_id', $user->id)->first();
        $this->assertNotNull($blacklistRecord->lifted_at);
    }

    // ============================================
    // CHANGE ROLE
    // ============================================

    public function test_system_admin_can_change_user_role(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent();

        $response = $this->actingAs($admin)->post(route('admin.users.change-role', $user), [
            'role_id' => $this->memberRole->id,
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertEquals($this->memberRole->id, $user->role_id);
    }

    public function test_cannot_downgrade_last_admin(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->post(route('admin.users.change-role', $admin), [
            'role_id' => $this->studentRole->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $admin->refresh();
        $this->assertEquals($this->systemAdminRole->id, $admin->role_id);
    }

    public function test_can_downgrade_admin_when_others_exist(): void
    {
        $admin1 = $this->createSystemAdmin();
        $admin2 = $this->createSystemAdmin([
            'email' => 'admin2@test.com',
            'full_name' => 'Admin Two',
        ]);

        $response = $this->actingAs($admin1)->post(route('admin.users.change-role', $admin2), [
            'role_id' => $this->studentRole->id,
        ]);

        $response->assertRedirect();
        $admin2->refresh();
        $this->assertEquals($this->studentRole->id, $admin2->role_id);
    }
}
