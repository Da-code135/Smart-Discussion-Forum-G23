<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class AdminAccessControlTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    // ============================================
    // DASHBOARD ACCESS
    // ============================================

    public function test_system_admin_can_access_dashboard(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);
    }

    public function test_group_admin_can_access_dashboard(): void
    {
        $admin = $this->createGroupAdmin();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);
    }

    public function test_regular_user_cannot_access_admin_dashboard(): void
    {
        $user = $this->createStudent();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('login'));
    }

    // ============================================
    // USER MANAGEMENT ACCESS
    // ============================================

    public function test_system_admin_can_access_user_management(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));
        $response->assertStatus(200);
    }

    public function test_group_admin_can_access_user_management(): void
    {
        $admin = $this->createGroupAdmin();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));
        $response->assertStatus(200);
    }

    public function test_regular_user_cannot_access_user_management(): void
    {
        $user = $this->createStudent();

        $response = $this->actingAs($user)->get(route('admin.users.index'));
        $response->assertStatus(403);
    }

    // ============================================
    // SYSTEM CONFIG ACCESS (System Admin only)
    // ============================================

    public function test_system_admin_can_access_system_config(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->get(route('admin.system-config.index'));
        $response->assertStatus(200);
    }

    public function test_group_admin_cannot_access_system_config(): void
    {
        $admin = $this->createGroupAdmin();

        $response = $this->actingAs($admin)->get(route('admin.system-config.index'));
        $response->assertStatus(403);
    }

    // ============================================
    // IP WHITELIST ACCESS (System Admin only)
    // ============================================

    public function test_system_admin_can_access_ip_whitelist(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->get(route('admin.ip-whitelist.index'));
        $response->assertStatus(200);
    }

    public function test_group_admin_cannot_access_ip_whitelist(): void
    {
        $admin = $this->createGroupAdmin();

        $response = $this->actingAs($admin)->get(route('admin.ip-whitelist.index'));
        $response->assertStatus(403);
    }

    // ============================================
    // AUDIT LOGS ACCESS
    // ============================================

    public function test_system_admin_can_access_audit_logs(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.index'));
        $response->assertStatus(200);
    }

    public function test_group_admin_can_access_audit_logs(): void
    {
        $admin = $this->createGroupAdmin();

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.index'));
        $response->assertStatus(200);
    }

    // ============================================
    // GROUP MANAGEMENT ACCESS
    // ============================================

    public function test_system_admin_can_access_groups(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->get(route('admin.groups.index'));
        $response->assertStatus(200);
    }

    public function test_group_admin_can_access_groups(): void
    {
        $admin = $this->createGroupAdmin();

        $response = $this->actingAs($admin)->get(route('admin.groups.index'));
        $response->assertStatus(200);
    }

    public function test_system_admin_can_create_groups(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->get(route('admin.groups.create'));
        $response->assertStatus(200);
    }

    public function test_group_admin_cannot_create_groups(): void
    {
        $admin = $this->createGroupAdmin();

        $response = $this->actingAs($admin)->get(route('admin.groups.create'));
        $response->assertStatus(403);
    }
}
