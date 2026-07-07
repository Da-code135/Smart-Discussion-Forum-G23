<?php

namespace App\Services;

use App\Models\BlacklistRecord;
use App\Models\User;
use App\Models\Warning;
use Illuminate\Support\Facades\DB;

class WarningService
{
    /**
     * Issue a new warning to a user
     *
     * @param  User  $user  The user to warn
     * @param  User  $admin  The admin issuing the warning
     * @param  string  $reason  The reason for the warning
     * @return Warning The created warning
     */
    public function issueWarning(User $user, User $admin, string $reason): Warning
    {
        // Get the user's active warnings
        $activeWarnings = $user->warnings()
            ->whereNull('is_resolved', true)
            ->orderBy('warning_number', 'desc')
            ->get();

        // Calculate the new warning number
        $warningNumber = $activeWarnings->isNotEmpty()
            ? $activeWarnings->first()->warning_number + 1
            : 1;

        // Create a database transaction to ensure data consistency
        return DB::transaction(function () use ($user, $admin, $reason, $warningNumber) {
            // Create the new warning
            $warning = Warning::create([
                'user_id' => $user->id,
                'warning_number' => $warningNumber,
                'reason' => $reason,
                'response_deadline' => now()->addDays(3), // 3-day response deadline
                'created_by' => $admin->id,
            ]);

            // Update user's account status to warned
            $user->update([
                'account_status' => 'warned',
                'is_warned' => true,
            ]);

            // Check if this is the third warning and auto-blacklist if needed
            if ($warningNumber >= 3) {
                $this->autoBlacklist($user, $admin);
            }

            return $warning;
        });
    }

    /**
     * Automatically blacklist a user after 3 warnings
     *
     * @param  User  $user  The user to blacklist
     * @param  User  $admin  The admin who triggered the blacklist
     * @return BlacklistRecord The created blacklist record
     */
    protected function autoBlacklist(User $user, User $admin): BlacklistRecord
    {
        // Create blacklist record
        $blacklistRecord = BlacklistRecord::create([
            'user_id' => $user->id,
            'reason' => 'Automatic blacklist after 3 warnings',
            'blacklisted_at' => now(),
        ]);

        // Update user's account status to blacklisted
        $user->update([
            'account_status' => 'blacklisted',
            'blacklisted_at' => now(),
        ]);

        return $blacklistRecord;
    }

    /**
     * Resolve a warning
     *
     * @param  Warning  $warning  The warning to resolve
     * @param  User  $admin  The admin resolving the warning
     * @return bool True if successful
     */
    public function resolveWarning(Warning $warning, User $admin): bool
    {
        // Update the warning
        $success = $warning->update([
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);

        if ($success) {
            // Check if all warnings are resolved
            $hasActiveWarnings = $warning->user->warnings()
                ->whereNull('is_resolved')
                ->exists();

            // If no more active warnings, update user's account status
            if (! $hasActiveWarnings) {
                $warning->user->update([
                    'account_status' => 'active',
                    'is_warned' => false,
                ]);
            }
        }

        return $success;
    }

    /**
     * Lift a blacklist record
     *
     * @param  BlacklistRecord  $blacklistRecord  The blacklist record to lift
     * @param  User  $admin  The admin lifting the blacklist
     * @return bool True if successful
     */
    public function liftBlacklist(BlacklistRecord $blacklistRecord, User $admin): bool
    {
        // Update the blacklist record
        $success = $blacklistRecord->update([
            'lifted_at' => now(),
            'lifted_by' => $admin->id,
        ]);

        if ($success) {
            // Update user's account status
            $blacklistRecord->user->update([
                'account_status' => 'active',
                'blacklisted_at' => null,
            ]);
        }

        return $success;
    }
}
