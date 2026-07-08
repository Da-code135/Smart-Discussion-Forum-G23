<?php

namespace Tests\Feature\Admin;

use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class GroupManagementTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    // ============================================
    // GROUP LIST
    // ============================================

    public function test_admin_can_view_groups(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->get(route('admin.groups.index'));
        $response->assertStatus(200);
        $response->assertViewIs('admin.groups.index');
    }

    public function test_admin_can_search_groups(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->get(route('admin.groups.index', ['search' => 'Default']));
        $response->assertStatus(200);
    }

    // ============================================
    // CREATE GROUP (System Admin only)
    // ============================================

    public function test_system_admin_can_create_group(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->post(route('admin.groups.store'), [
            'group_name' => 'New Test Group',
            'description' => 'A test group',
            'group_type' => 'student',
        ]);

        $response->assertRedirect(route('admin.groups.index'));
        $this->assertDatabaseHas('groups', ['group_name' => 'New Test Group']);
    }

    public function test_group_name_must_be_unique(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->post(route('admin.groups.store'), [
            'group_name' => 'Default Group',
            'description' => 'Duplicate',
            'group_type' => 'student',
        ]);

        $response->assertSessionHasErrors('group_name');
    }

    public function test_group_admin_cannot_create_group(): void
    {
        $admin = $this->createGroupAdmin();

        $response = $this->actingAs($admin)->post(route('admin.groups.store'), [
            'group_name' => 'Unauthorized Group',
            'description' => 'Should fail',
        ]);

        $response->assertStatus(403);
    }

    // ============================================
    // EDIT GROUP
    // ============================================

    public function test_system_admin_can_edit_group(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->put(route('admin.groups.update', $this->defaultGroup), [
            'group_name' => 'Updated Group Name',
            'description' => 'Updated description',
            'group_type' => 'student',
        ]);

        $response->assertRedirect(route('admin.groups.index'));
        $this->assertDatabaseHas('groups', ['group_name' => 'Updated Group Name']);
    }

    // ============================================
    // DELETE GROUP
    // ============================================

    public function test_system_admin_can_delete_group(): void
    {
        $admin = $this->createSystemAdmin();
        $group = Group::create(['group_name' => 'To Delete', 'description' => 'Will be deleted', 'group_type' => 'student']);

        $response = $this->actingAs($admin)->delete(route('admin.groups.destroy', $group));

        $response->assertRedirect(route('admin.groups.index'));
    }

    public function test_cannot_delete_general_group(): void
    {
        $admin = $this->createSystemAdmin();
        $general = Group::create(['group_name' => 'General', 'description' => 'General group', 'group_type' => 'student']);

        $response = $this->actingAs($admin)->delete(route('admin.groups.destroy', $general));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('groups', ['group_name' => 'General']);
    }

    // ============================================
    // GROUP MEMBERS
    // ============================================

    public function test_system_admin_can_view_group_members(): void
    {
        $admin = $this->createSystemAdmin();
        $this->createStudent();

        $response = $this->actingAs($admin)->get(route('admin.groups.members', $this->defaultGroup));
        $response->assertStatus(200);
    }

    public function test_system_admin_can_update_group_members(): void
    {
        $admin = $this->createSystemAdmin();
        $student = $this->createStudent();

        $response = $this->actingAs($admin)->put(route('admin.groups.update-members', $this->defaultGroup), [
            'user_ids' => [$student->id],
        ]);

        $response->assertRedirect();
    }

    // ============================================
    // BULK ASSIGN
    // ============================================

    public function test_system_admin_can_bulk_assign_users(): void
    {
        $admin = $this->createSystemAdmin();
        $student = $this->createStudent();

        $response = $this->actingAs($admin)->post(route('admin.groups.bulk-assign'), [
            'user_ids' => [$student->id],
            'group_id' => $this->secondGroup->id,
        ]);

        $response->assertRedirect();
        $student->refresh();
        $this->assertEquals($this->secondGroup->id, $student->group_id);
    }

    public function test_group_admin_cannot_bulk_assign(): void
    {
        $admin = $this->createGroupAdmin();
        $student = $this->createStudent();

        $response = $this->actingAs($admin)->post(route('admin.groups.bulk-assign'), [
            'user_ids' => [$student->id],
            'group_id' => $this->secondGroup->id,
        ]);

        $response->assertStatus(403);
    }
}
