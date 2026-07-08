<?php

namespace App\Utilities;

use App\Models\User;
use App\Models\Warning;

/**
 * Shared utility for warning acknowledgement used by both web and API controllers.
 *
 * When a user's account status is 'warned', they must acknowledge the warning
 * before they can continue using the platform. This utility handles the
 * acknowledgement logic consistently.
 */
class WarningAcknowledgementUtility
{
    /**
     * Check if the user has any unacknowledged warnings.
     */
    public function hasUnacknowledgedWarning(User $user): bool
    {
        return $user->warnings()
            ->where('is_acknowledged', false)
            ->exists();
    }

    /**
     * Get the user's first unacknowledged warning.
     */
    public function getUnacknowledgedWarning(User $user): ?Warning
    {
        return $user->warnings()
            ->where('is_acknowledged', false)
            ->first();
    }

    /**
     * Acknowledge a warning.
     *
     * @param  Warning  $warning  The warning to acknowledge
     * @param  User  $user  The user acknowledging the warning (must be the warned user)
     * @return bool True if acknowledged successfully
     *
     * @throws \RuntimeException If the user is not the owner of the warning
     */
    public function acknowledge(Warning $warning, User $user): bool
    {
        if ($warning->user_id !== $user->id) {
            throw new \RuntimeException('You can only acknowledge your own warnings.');
        }

        if ($warning->is_acknowledged) {
            return false; // Already acknowledged
        }

        $warning->update([
            'is_acknowledged' => true,
        ]);

        // Check if all warnings are resolved/acknowledged
        $hasActiveWarnings = $user->warnings()
            ->whereNull('is_resolved')
            ->exists();

        // If no more active warnings, restore account status to active
        if (! $hasActiveWarnings) {
            $user->update([
                'account_status' => 'active',
                'is_warned' => false,
            ]);
        }

        return true;
    }
}
