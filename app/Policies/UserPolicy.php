<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine if the given user can view users
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the given user can view the user
     */
    public function view(User $user, User $targetUser): bool
    {
        return $user->canAdminUser($targetUser);
    }

    /**
     * Determine if the given user can update the user
     * Only System Admins can change user roles
     */
    public function update(User $user, User $targetUser): bool
    {
        // System admins can update any user
        if ($user->isSystemAdmin()) {
            return true;
        }

        // Group admins can only update users in their groups
        if ($user->isGroupAdmin()) {
            return $user->canAdminUser($targetUser);
        }

        return false;
    }

    /**
     * Determine if the given user can change the role of the user
     * Only System Admins can change roles
     */
    public function changeRole(User $user, User $targetUser): bool
    {
        return $user->isSystemAdmin();
    }

    /**
     * Determine if the given user can blacklist the user
     */
    public function blacklist(User $user, User $targetUser): bool
    {
        // System admins can blacklist any user
        if ($user->isSystemAdmin()) {
            return true;
        }

        // Group admins can only blacklist users in their groups
        if ($user->isGroupAdmin()) {
            return $user->canAdminUser($targetUser);
        }

        return false;
    }

    /**
     * Determine if the given user can lift blacklist
     */
    public function liftBlacklist(User $user, User $targetUser): bool
    {
        return $this->blacklist($user, $targetUser);
    }

    /**
     * Determine if the given user can warn the user
     */
    public function warn(User $user, User $targetUser): bool
    {
        return $this->blacklist($user, $targetUser);
    }

    /**
     * Determine if the given user can delete the user
     * Only System Admins can delete users
     */
    public function delete(User $user, User $targetUser): bool
    {
        return $user->isSystemAdmin();
    }
}
