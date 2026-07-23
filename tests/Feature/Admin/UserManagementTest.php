<?php

namespace Tests\Feature\Admin;

use App\Models\BlacklistRecord;
use App\Models\User;
use App\Models\Warning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

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
            'group_id' => $this->defaultGroup->id,
        ]);

        $response = $this->actingAs($admin1)->post(route('admin.users.change-role', $admin2), [
            'role_id' => $this->studentRole->id,
        ]);

        $response->assertRedirect();
        $admin2->refresh();
        $this->assertEquals($this->studentRole->id, $admin2->role_id);
    }

    // ============================================
    // SHOW USER DETAIL PAGE
    // ============================================

    public function test_admin_can_view_user_detail_page(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent();

        $response = $this->actingAs($admin)->get(route('admin.users.show', $user));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.show');
        $response->assertViewHas('user');
    }

    public function test_group_admin_cannot_view_user_outside_scope(): void
    {
        $groupAdmin = $this->createGroupAdmin();
        $user = $this->createStudent(['group_id' => $this->secondGroup->id]);

        $response = $this->actingAs($groupAdmin)->get(route('admin.users.show', $user));
        $response->assertStatus(403);
    }

    // ============================================
    // CREATE USER
    // ============================================

    public function test_system_admin_can_create_user(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'full_name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role_id' => $this->memberRole->id,
            'group_id' => $this->defaultGroup->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'full_name' => 'New User',
            'email' => 'newuser@test.com',
            'role_id' => $this->memberRole->id,
            'account_status' => 'active',
        ]);
    }

    public function test_create_user_requires_valid_password(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'full_name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'role_id' => $this->memberRole->id,
            'group_id' => $this->defaultGroup->id,
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_group_admin_cannot_create_user(): void
    {
        $groupAdmin = $this->createGroupAdmin();

        $response = $this->actingAs($groupAdmin)->post(route('admin.users.store'), [
            'full_name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role_id' => $this->memberRole->id,
            'group_id' => $this->defaultGroup->id,
        ]);

        $response->assertStatus(403);
    }

    // ============================================
    // EDIT USER
    // ============================================

    public function test_system_admin_can_edit_user(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'full_name' => 'Updated Name',
            'email' => $user->email,
            'role_id' => $this->memberRole->id,
            'group_id' => $this->defaultGroup->id,
            'account_status' => 'active',
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertEquals('Updated Name', $user->full_name);
    }

    public function test_edit_user_validates_email_uniqueness(): void
    {
        $admin = $this->createSystemAdmin();
        $user1 = $this->createStudent();
        $user2 = $this->createMember(['email' => 'other@test.com']);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user1), [
            'full_name' => $user1->full_name,
            'email' => 'other@test.com', // Taken by user2
            'role_id' => $this->studentRole->id,
            'group_id' => $this->defaultGroup->id,
            'account_status' => 'active',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_edit_user_cannot_downgrade_last_admin(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'full_name' => $admin->full_name,
            'email' => $admin->email,
            'role_id' => $this->memberRole->id,
            'group_id' => $this->defaultGroup->id,
            'account_status' => 'active',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $admin->refresh();
        $this->assertEquals($this->systemAdminRole->id, $admin->role_id);
    }

    // ============================================
    // DELETE USER
    // ============================================

    public function test_system_admin_can_delete_user(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $user));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_group_admin_cannot_delete_user(): void
    {
        $groupAdmin = $this->createGroupAdmin();
        $user = $this->createStudent();

        $response = $this->actingAs($groupAdmin)->delete(route('admin.users.destroy', $user));

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    // ============================================
    // RESET PASSWORD
    // ============================================

    public function test_system_admin_can_reset_password(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent();

        $response = $this->actingAs($admin)->post(route('admin.users.reset-password.store', $user), [
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertRedirect(route('admin.users.show', $user));
        $response->assertSessionHas('success');

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123', $user->password));
    }

    public function test_reset_password_requires_strong_password(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent();

        $response = $this->actingAs($admin)->post(route('admin.users.reset-password.store', $user), [
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_group_admin_cannot_reset_password(): void
    {
        $groupAdmin = $this->createGroupAdmin();
        $user = $this->createStudent();

        $response = $this->actingAs($groupAdmin)->post(route('admin.users.reset-password.store', $user), [
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(403);
    }

    // ============================================
    // BLACKLIST USER
    // ============================================

    public function test_system_admin_can_blacklist_user(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent();

        $response = $this->actingAs($admin)->post(route('admin.users.blacklist.store', $user), [
            'reason' => 'Violation of terms',
            'duration_days' => 30,
        ]);

        $response->assertRedirect(route('admin.users.show', $user));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals('blacklisted', $user->account_status);

        $blacklistRecord = BlacklistRecord::where('user_id', $user->id)->first();
        $this->assertNotNull($blacklistRecord);
        $this->assertEquals('Violation of terms', $blacklistRecord->reason);
        $this->assertNotNull($blacklistRecord->expires_at);
    }

    public function test_system_admin_can_permanently_blacklist_user(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent();

        $response = $this->actingAs($admin)->post(route('admin.users.blacklist.store', $user), [
            'reason' => 'Permanent ban',
            'duration_days' => null,
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertEquals('blacklisted', $user->account_status);

        $blacklistRecord = BlacklistRecord::where('user_id', $user->id)->first();
        $this->assertNull($blacklistRecord->expires_at);
    }

    public function test_blacklist_requires_reason(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent();

        $response = $this->actingAs($admin)->post(route('admin.users.blacklist.store', $user), [
            'duration_days' => 30,
        ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_group_admin_cannot_blacklist_user(): void
    {
        $groupAdmin = $this->createGroupAdmin();
        $user = $this->createStudent();

        $response = $this->actingAs($groupAdmin)->post(route('admin.users.blacklist.store', $user), [
            'reason' => 'Test reason',
        ]);

        $response->assertStatus(403);
    }

    // ============================================
    // RESOLVE WARNING
    // ============================================

    public function test_system_admin_can_resolve_warning(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent(['account_status' => 'warned']);

        $warning = Warning::create([
            'user_id' => $user->id,
            'warning_number' => 1,
            'reason' => 'Test warning',
            'response_deadline' => now()->addDays(7),
            'is_acknowledged' => false,
            'is_resolved' => false,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.warnings.resolve', $warning));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $warning->refresh();
        $this->assertTrue($warning->is_resolved);
        $this->assertNotNull($warning->resolved_at);
    }

    public function test_resolving_last_warning_activates_user(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent(['account_status' => 'warned']);

        $warning = Warning::create([
            'user_id' => $user->id,
            'warning_number' => 1,
            'reason' => 'Test warning',
            'response_deadline' => now()->addDays(7),
            'is_acknowledged' => true,
            'is_resolved' => false,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.warnings.resolve', $warning));

        $user->refresh();
        $this->assertEquals('active', $user->account_status);
    }

    public function test_cannot_resolve_already_resolved_warning(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent();

        $warning = Warning::create([
            'user_id' => $user->id,
            'warning_number' => 1,
            'reason' => 'Test warning',
            'response_deadline' => now()->addDays(7),
            'is_acknowledged' => true,
            'is_resolved' => true,
            'resolved_at' => now(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.warnings.resolve', $warning));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_group_admin_cannot_resolve_warning(): void
    {
        $groupAdmin = $this->createGroupAdmin();
        $user = $this->createStudent();

        $warning = Warning::create([
            'user_id' => $user->id,
            'warning_number' => 1,
            'reason' => 'Test warning',
            'response_deadline' => now()->addDays(7),
            'is_acknowledged' => false,
            'is_resolved' => false,
            'created_by' => $groupAdmin->id,
        ]);

        $response = $this->actingAs($groupAdmin)->post(route('admin.warnings.resolve', $warning));

        $response->assertStatus(403);
    }
}
