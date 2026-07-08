<?php

namespace Tests;

use App\Models\Group;
use App\Models\Role;
use App\Models\User;

trait CreatesTestUsers
{
    protected Role $systemAdminRole;

    protected Role $groupAdminRole;

    protected Role $studentRole;

    protected Role $memberRole;

    protected Group $defaultGroup;

    protected Group $secondGroup;

    protected function seedRolesAndGroups(): void
    {
        $this->systemAdminRole = Role::firstOrCreate(
            ['role_name' => 'System Administrator'],
            ['description' => 'Full system-wide access']
        );

        $this->groupAdminRole = Role::firstOrCreate(
            ['role_name' => 'Group Administrator'],
            ['description' => 'Can manage assigned groups']
        );

        $this->studentRole = Role::firstOrCreate(
            ['role_name' => 'Student'],
            ['description' => 'Student role']
        );

        $this->memberRole = Role::firstOrCreate(
            ['role_name' => 'Member'],
            ['description' => 'Member role']
        );

        $this->defaultGroup = Group::firstOrCreate(
            ['group_name' => 'Default Group'],
            ['description' => 'Default test group', 'group_type' => 'student']
        );

        $this->secondGroup = Group::firstOrCreate(
            ['group_name' => 'Second Group'],
            ['description' => 'Second test group', 'group_type' => 'student']
        );
    }

    protected function createSystemAdmin(array $attrs = []): User
    {
        return User::create(array_merge([
            'full_name' => 'System Admin',
            'email' => 'sysadmin-'.uniqid().'@test.com',
            'password' => 'Password123',
            'role_id' => $this->systemAdminRole->id,
            'group_id' => null,
            'account_status' => 'active',
        ], $attrs));
    }

    protected function createGroupAdmin(array $attrs = []): User
    {
        return User::create(array_merge([
            'full_name' => 'Group Admin',
            'email' => 'groupadmin-'.uniqid().'@test.com',
            'password' => 'Password123',
            'role_id' => $this->groupAdminRole->id,
            'group_id' => $this->defaultGroup->id,
            'account_status' => 'active',
        ], $attrs));
    }

    protected function createStudent(array $attrs = []): User
    {
        return User::create(array_merge([
            'full_name' => 'Test Student',
            'email' => 'student-'.uniqid().'@test.com',
            'password' => 'Password123',
            'role_id' => $this->studentRole->id,
            'group_id' => $this->defaultGroup->id,
            'account_status' => 'active',
        ], $attrs));
    }

    protected function createMember(array $attrs = []): User
    {
        return User::create(array_merge([
            'full_name' => 'Test Member',
            'email' => 'member-'.uniqid().'@test.com',
            'password' => 'Password123',
            'role_id' => $this->memberRole->id,
            'group_id' => $this->defaultGroup->id,
            'account_status' => 'active',
        ], $attrs));
    }
}
