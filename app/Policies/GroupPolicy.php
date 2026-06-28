<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    /**
     * Determine if the given user can view groups
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the given user can view the group
     */
    public function view(User $user, Group $group): bool
    {
        return $user->canAdminGroup($group);
    }

    /**
     * Determine if the given user can create groups
     * Only System Admins can create new groups
     */
    public function create(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    /**
     * Determine if the given user can update the group
     */
    public function update(User $user, Group $group): bool
    {
        // System admins can update any group
        if ($user->isSystemAdmin()) {
            return true;
        }

        // Group admins can only update their assigned groups
        if ($user->isGroupAdmin()) {
            return $user->canAdminGroup($group);
        }

        return false;
    }

    /**
     * Determine if the given user can delete the group
     * Only System Admins can delete groups
     */
    public function delete(User $user, Group $group): bool
    {
        return $user->isSystemAdmin();
    }

    /**
     * Determine if user can manage members of this group
     */
    public function manageMembers(User $user, Group $group): bool
    {
        return $user->canAdminGroup($group);
    }

    /**
     * Determine if user can assign admins to this group
     * Only System Admins can assign group admins
     */
    public function assignAdmin(User $user, Group $group): bool
    {
        return $user->isSystemAdmin();
    }
}
