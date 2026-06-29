<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Warning;
use App\Models\BlacklistRecord;
use App\Models\SystemConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorMemberActivity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:activity {--dry-run : Run without making database changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor member activity and issue warnings/blacklist';

    /**
     * Counter for blacklisted users.
     */
    private int $blacklistedCount = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('[DRY RUN MODE] No database changes will be made.');
            $this->newLine();
        }

        $this->info('Starting activity monitoring...');
        $this->newLine();

        // Query all active and warned users
        $users = User::whereIn('account_status', ['active', 'warned'])->get();

        if ($users->isEmpty()) {
            $this->info('No active or warned users found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$users->count()} users to check.");
        $this->newLine();

        // Fetch inactivity threshold from config
        $inactivityThreshold = (int) SystemConfig::getValue('inactivity_warning_days', 30);
        $this->info("Inactivity threshold: {$inactivityThreshold} days");
        $this->newLine();

        $warnedCount = 0;
        $skippedCount = 0;

        foreach ($users as $user) {
            // Calculate days since last activity
            $lastActive = $user->last_active_at ?? $user->created_at;
            $daysInactive = now()->diffInDays($lastActive, absolute: true);

            $this->line("Checking: {$user->full_name} ({$user->email}) - {$daysInactive} days inactive");

            if ($daysInactive >= $inactivityThreshold) {
                if ($isDryRun) {
                    $this->warn("[DRY RUN] Would issue warning to {$user->full_name}");
                    $warnedCount++;
                } else {
                    $this->issueWarning($user);
                    $warnedCount++;
                }
            } else {
                $skippedCount++;
            }
        }

        $this->newLine();
        $this->info('Activity monitoring complete!');
        $this->info("Results:");
        $this->line("  - Warned: {$warnedCount}");
        $this->line("  - Blacklisted: {$this->blacklistedCount}");
        $this->line("  - Skipped (active): {$skippedCount}");

        if ($isDryRun) {
            $this->warn('[DRY RUN] No changes were saved to the database.');
        }

        return Command::SUCCESS;
    }

    /**
     * Issue a warning to an inactive user.
     * Implements 3-step escalation per SDD 5.1.3:
     * - Inactive (no warnings) → Warning 1
     * - Warning 1 expired (deadline passed, not acknowledged) → Warning 2
     * - Warning 2 expired → Blacklist
     *
     * @param User $user
     * @return void
     */
    private function issueWarning(User $user): void
    {
        // Fetch warning response days from config
        $warningResponseDays = (int) SystemConfig::getValue('warning_response_days', 7);

        // Check for existing unresolved warnings
        $unresolvedWarnings = Warning::where('user_id', $user->id)
            ->where('is_resolved', false)
            ->orderBy('warning_number', 'desc')
            ->get();

        if ($unresolvedWarnings->isEmpty()) {
            // No warnings yet → Issue Warning 1
            Warning::create([
                'user_id' => $user->id,
                'warning_number' => 1,
                'reason' => 'Account inactivity - No activity for extended period',
                'response_deadline' => now()->addDays($warningResponseDays),
                'is_acknowledged' => false,
                'is_resolved' => false,
            ]);

            if ($user->account_status !== 'warned') {
                $user->update(['account_status' => 'warned']);
            }

            $this->line("  <fg=yellow>✓ Warning 1 issued</> - Deadline: {$warningResponseDays} days");
            Log::info("Warning 1 issued to user {$user->id} ({$user->email}) for inactivity");
            return;
        }

        $latestWarning = $unresolvedWarnings->first();

        if ($latestWarning->warning_number === 1 && $latestWarning->response_deadline->isPast()) {
            // Warning 1 expired (deadline passed, not acknowledged) → Issue Warning 2
            Warning::create([
                'user_id' => $user->id,
                'warning_number' => 2,
                'reason' => 'Account inactivity - Failed to respond to Warning 1',
                'response_deadline' => now()->addDays($warningResponseDays),
                'is_acknowledged' => false,
                'is_resolved' => false,
            ]);

            $this->line("  <fg=yellow>⚠ Warning 2 issued</> - Deadline: {$warningResponseDays} days");
            Log::info("Warning 2 issued to user {$user->id} ({$user->email}) - Warning 1 expired unacknowledged");
            return;
        }

        if ($latestWarning->warning_number === 2 && $latestWarning->response_deadline->isPast()) {
            // Warning 2 expired → Blacklist user
            $this->blacklistUser($user);
            return;
        }

        // Warning exists but deadline hasn't passed yet - no action needed
        $this->line("  <comment>User has active warning - deadline not yet passed</comment>");
    }

    /**
     * Blacklist an inactive user.
     *
     * @param User $user
     * @return void
     */
    private function blacklistUser(User $user): void
    {
        // Fetch blacklist duration from config
        $blacklistDuration = (int) SystemConfig::getValue('blacklist_duration_days', 90);

        // Create blacklist record
        BlacklistRecord::create([
            'user_id' => $user->id,
            'reason' => 'Inactivity - Failed to respond to warning',
            'expires_at' => now()->addDays($blacklistDuration),
            'lifted_at' => null,
            'lifted_by' => null,
        ]);

        // Update user status to blacklisted
        $user->update(['account_status' => 'blacklisted']);

        $this->blacklistedCount++;

        $this->line("  <fg=red>✗ User blacklisted</> - Duration: {$blacklistDuration} days");

        Log::warning("User {$user->id} ({$user->email}) blacklisted for inactivity");
    }
}
