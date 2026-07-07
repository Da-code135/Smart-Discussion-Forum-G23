<?php

namespace Tests\Unit\Models;

use App\Models\BlacklistRecord;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Models\Warning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    // ============================================
    // ROLE CHECK HELPERS
    // ============================================

    public function test_is_system_admin_returns_true_for_system_admin(): void
    {
        $admin = $this->createSystemAdmin();
        $this->assertTrue($admin->isSystemAdmin());
    }

    public function test_is_system_admin_returns_false_for_group_admin(): void
    {
        $admin = $this->createGroupAdmin();
        $this->assertFalse($admin->isSystemAdmin());
    }

    public function test_is_system_admin_returns_false_for_student(): void
    {
        $user = $this->createStudent();
        $this->assertFalse($user->isSystemAdmin());
    }

    public function test_is_group_admin_returns_true_for_group_admin(): void
    {
        $admin = $this->createGroupAdmin();
        $this->assertTrue($admin->isGroupAdmin());
    }

    public function test_is_group_admin_returns_false_for_system_admin(): void
    {
        $admin = $this->createSystemAdmin();
        $this->assertFalse($admin->isGroupAdmin());
    }

    public function test_is_admin_returns_true_for_system_admin(): void
    {
        $admin = $this->createSystemAdmin();
        $this->assertTrue($admin->isAdmin());
    }

    public function test_is_admin_returns_true_for_group_admin(): void
    {
        $admin = $this->createGroupAdmin();
        $this->assertTrue($admin->isAdmin());
    }

    public function test_is_admin_returns_false_for_student(): void
    {
        $user = $this->createStudent();
        $this->assertFalse($user->isAdmin());
    }

    // ============================================
    // GROUP ADMIN PERMISSIONS
    // ============================================

    public function test_system_admin_can_admin_any_group(): void
    {
        $admin = $this->createSystemAdmin();
        $this->assertTrue($admin->canAdminGroup($this->defaultGroup));
        $this->assertTrue($admin->canAdminGroup($this->secondGroup));
    }

    public function test_group_admin_can_admin_assigned_group(): void
    {
        $admin = $this->createGroupAdmin();
        $admin->administeredGroups()->attach($this->defaultGroup->id, [
            'assigned_by' => $admin->id,
        ]);

        $this->assertTrue($admin->canAdminGroup($this->defaultGroup));
    }

    public function test_group_admin_cannot_admin_unassigned_group(): void
    {
        $admin = $this->createGroupAdmin();
        $admin->administeredGroups()->attach($this->defaultGroup->id, [
            'assigned_by' => $admin->id,
        ]);

        $this->assertFalse($admin->canAdminGroup($this->secondGroup));
    }

    public function test_student_cannot_admin_any_group(): void
    {
        $user = $this->createStudent();
        $this->assertFalse($user->canAdminGroup($this->defaultGroup));
    }

    // ============================================
    // USER ADMIN PERMISSIONS
    // ============================================

    public function test_system_admin_can_admin_any_user(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createStudent();

        $this->assertTrue($admin->canAdminUser($user));
    }

    public function test_group_admin_can_admin_user_in_assigned_group(): void
    {
        $admin = $this->createGroupAdmin();
        $admin->administeredGroups()->attach($this->defaultGroup->id, [
            'assigned_by' => $admin->id,
        ]);

        $user = $this->createStudent();

        $this->assertTrue($admin->canAdminUser($user));
    }

    public function test_group_admin_cannot_admin_user_in_other_group(): void
    {
        $admin = $this->createGroupAdmin();
        $admin->administeredGroups()->attach($this->defaultGroup->id, [
            'assigned_by' => $admin->id,
        ]);

        $user = $this->createStudent([
            'group_id' => $this->secondGroup->id,
            'email' => 'other@test.com',
        ]);

        $this->assertFalse($admin->canAdminUser($user));
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function test_user_belongs_to_role(): void
    {
        $user = $this->createStudent();
        $this->assertNotNull($user->role);
        $this->assertEquals('Student', $user->role->role_name);
    }

    public function test_user_belongs_to_group(): void
    {
        $user = $this->createStudent();
        $this->assertNotNull($user->group);
        $this->assertEquals('Default Group', $user->group->group_name);
    }

    public function test_user_has_many_warnings(): void
    {
        $user = $this->createStudent();

        Warning::create([
            'user_id' => $user->id,
            'warning_number' => 1,
            'reason' => 'Test',
            'response_deadline' => now()->addDays(7),
            'is_acknowledged' => false,
            'is_resolved' => false,
        ]);

        $this->assertEquals(1, $user->warnings()->count());
    }

    public function test_user_has_many_blacklist_records(): void
    {
        $user = $this->createStudent(['account_status' => 'blacklisted']);

        BlacklistRecord::create([
            'user_id' => $user->id,
            'reason' => 'Test',
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertEquals(1, $user->blacklistRecords()->count());
    }

    // ============================================
    // ADMINISTERED GROUPS
    // ============================================

    public function test_group_admin_can_have_administered_groups(): void
    {
        $admin = $this->createGroupAdmin();
        $admin->administeredGroups()->attach($this->defaultGroup->id, [
            'assigned_by' => $admin->id,
        ]);

        $this->assertEquals(1, $admin->administeredGroups()->count());
        $this->assertEquals('Default Group', $admin->administeredGroups->first()->group_name);
    }
}
