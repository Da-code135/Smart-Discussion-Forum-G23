<?php

namespace App\Policies;

use App\Models\Warning;
use App\Models\User;

class WarningPolicy
{
    /**
     * Determine if the given user can view a warning
     */
    public function view(User $user, Warning $warning): bool
    {
        // System Admins can view any warning
        if ($user->isSystemAdmin()) {
            return true;
        }

        // Group Admins can only view warnings for users in their groups
        if ($user->isGroupAdmin()) {
            return $user->canAdminUser($warning->user);
        }

        return false;
    }

    /**
     * Determine if the given user can create (issue) a warning
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the given user can update (resolve) a warning
     * Only System Admins can resolve warnings
     */
    public function update(User $user, Warning $warning): bool
    {
        return $user->isSystemAdmin();
    }
}
