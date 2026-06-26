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
        $blacklistedCount = 0;
        $skippedCount = 0;

        foreach ($users as $user) {
            // Calculate days since last activity
            $lastActive = $user->last_active_at ?? $user->created_at;
            $daysInactive = now()->diffInDays($lastActive);

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
        $this->line("  - Blacklisted: {$blacklistedCount}");
        $this->line("  - Skipped (active): {$skippedCount}");

        if ($isDryRun) {
            $this->warn('[DRY RUN] No changes were saved to the database.');
        }

        return Command::SUCCESS;
    }

    /**
     * Issue a warning to an inactive user.
     *
     * @param User $user
     * @return void
     */
    private function issueWarning(User $user): void
    {
        // Check if user already has an unresolved warning to avoid duplicates
        $existingWarning = Warning::where('user_id', $user->id)
            ->where('is_resolved', false)
            ->first();

        if ($existingWarning) {
            $this->line("  <comment>User already has an unresolved warning. Checking for blacklist...</comment>");

            // If user is already warned and has unresolved warning, blacklist them
            if ($user->account_status === 'warned') {
                $this->blacklistUser($user);
            }
            return;
        }

        // Fetch warning response days from config
        $warningResponseDays = (int) SystemConfig::getValue('warning_response_days', 7);

        // Create warning record
        Warning::create([
            'user_id' => $user->id,
            'warning_number' => 1,
            'reason' => 'Account inactivity - No activity for extended period',
            'response_deadline' => now()->addDays($warningResponseDays),
            'is_acknowledged' => false,
            'is_resolved' => false,
        ]);

        // Update user status to warned (if not already)
        if ($user->account_status !== 'warned') {
            $user->update(['account_status' => 'warned']);
        }

        $this->line("  <fg=yellow>✓ Warning issued</> - Deadline: {$warningResponseDays} days");

        Log::info("Warning issued to user {$user->id} ({$user->email}) for inactivity");
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

        $this->line("  <fg=red>✗ User blacklisted</> - Duration: {$blacklistDuration} days");

        Log::warning("User {$user->id} ({$user->email}) blacklisted for inactivity");
    }
}
