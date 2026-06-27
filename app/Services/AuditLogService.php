<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * Log an administrative action
     *
     * @param string $action The action being logged
     * @param mixed $target The target model (optional)
     * @param array $oldValues Old values before change (optional)
     * @param array $newValues New values after change (optional)
     * @param string $description Human-readable description (optional)
     * @param int|null $userId User who performed action (defaults to current user)
     * @return AuditLog
     */
    public function log(
        string $action,
        $target = null,
        array $oldValues = [],
        array $newValues = [],
        string $description = '',
        int $userId = null
    ): AuditLog
    {
        $request = request();
        
        return AuditLog::create([
            'user_id' => $userId ?? (Auth::id() ?: null),
            'action' => $action,
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target?->id,
            'old_values' => !empty($oldValues) ? $oldValues : null,
            'new_values' => !empty($newValues) ? $newValues : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'description' => $description ?: $this->generateDescription($action, $target),
        ]);
    }

    /**
     * Generate automatic description based on action and target
     */
    protected function generateDescription(string $action, $target): string
    {
        $userName = Auth::user()?->full_name ?? 'System';
        $targetName = '';

        if ($target) {
            if (method_exists($target, 'getName')) {
                $targetName = $target->getName();
            } elseif (isset($target->full_name)) {
                $targetName = $target->full_name;
            } elseif (isset($target->group_name)) {
                $targetName = $target->group_name;
            } elseif (isset($target->email)) {
                $targetName = $target->email;
            }
        }

        $descriptions = [
            'user.role.changed' => "{$userName} changed role for user {$targetName}",
            'user.group.changed' => "{$userName} changed group for user {$targetName}",
            'user.blacklisted' => "{$userName} blacklisted user {$targetName}",
            'user.blacklist.lifted' => "{$userName} lifted blacklist for user {$targetName}",
            'user.warned' => "{$userName} issued warning to user {$targetName}",
            'user.activated' => "{$userName} activated user {$targetName}",
            'user.deleted' => "{$userName} deleted user {$targetName}",
            'group.created' => "{$userName} created group {$targetName}",
            'group.updated' => "{$userName} updated group {$targetName}",
            'group.deleted' => "{$userName} deleted group {$targetName}",
            'group.member.added' => "{$userName} added member to group {$targetName}",
            'group.member.removed' => "{$userName} removed member from group {$targetName}",
            'system.config.updated' => "{$userName} updated system configuration",
            'admin.ip.added' => "{$userName} added IP to whitelist",
            'admin.ip.removed' => "{$userName} removed IP from whitelist",
        ];

        return $descriptions[$action] ?? "{$userName} performed {$action}";
    }

    /**
     * Log user role change
     */
    public function logUserRoleChange($user, int $oldRoleId, int $newRoleId): AuditLog
    {
        return $this->log(
            action: 'user.role.changed',
            target: $user,
            oldValues: ['role_id' => $oldRoleId],
            newValues: ['role_id' => $newRoleId]
        );
    }

    /**
     * Log user group change
     */
    public function logUserGroupChange($user, int $oldGroupId, int $newGroupId): AuditLog
    {
        return $this->log(
            action: 'user.group.changed',
            target: $user,
            oldValues: ['group_id' => $oldGroupId],
            newValues: ['group_id' => $newGroupId]
        );
    }

    /**
     * Log user blacklisting
     */
    public function logUserBlacklisted($user, string $reason, $expiresAt = null): AuditLog
    {
        return $this->log(
            action: 'user.blacklisted',
            target: $user,
            newValues: [
                'reason' => $reason,
                'expires_at' => $expiresAt?->toDateTimeString(),
            ]
        );
    }

    /**
     * Log blacklist lifted
     */
    public function logBlacklistLifted($user): AuditLog
    {
        return $this->log(
            action: 'user.blacklist.lifted',
            target: $user
        );
    }

    /**
     * Log user warning
     */
    public function logUserWarned($user, string $reason): AuditLog
    {
        return $this->log(
            action: 'user.warned',
            target: $user,
            newValues: ['reason' => $reason]
        );
    }

    /**
     * Log user activation
     */
    public function logUserActivated($user): AuditLog
    {
        return $this->log(
            action: 'user.activated',
            target: $user
        );
    }

    /**
     * Log user deletion
     */
    public function logUserDeleted($user): AuditLog
    {
        return $this->log(
            action: 'user.deleted',
            target: $user,
            oldValues: [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
            ]
        );
    }

    /**
     * Log group creation
     */
    public function logGroupCreated($group): AuditLog
    {
        return $this->log(
            action: 'group.created',
            target: $group,
            newValues: [
                'group_name' => $group->group_name,
                'description' => $group->description,
            ]
        );
    }

    /**
     * Log group update
     */
    public function logGroupUpdated($group, array $oldValues): AuditLog
    {
        return $this->log(
            action: 'group.updated',
            target: $group,
            oldValues: $oldValues,
            newValues: $group->only(['group_name', 'description'])
        );
    }

    /**
     * Log group deletion
     */
    public function logGroupDeleted($group): AuditLog
    {
        return $this->log(
            action: 'group.deleted',
            target: $group,
            oldValues: [
                'id' => $group->id,
                'group_name' => $group->group_name,
            ]
        );
    }

    /**
     * Log group member addition
     */
    public function logGroupMemberAdded($group, $user): AuditLog
    {
        return $this->log(
            action: 'group.member.added',
            target: $group,
            newValues: [
                'user_id' => $user->id,
                'user_name' => $user->full_name,
            ]
        );
    }

    /**
     * Log group member removal
     */
    public function logGroupMemberRemoved($group, $user): AuditLog
    {
        return $this->log(
            action: 'group.member.removed',
            target: $group,
            oldValues: [
                'user_id' => $user->id,
                'user_name' => $user->full_name,
            ]
        );
    }

    /**
     * Log system configuration update
     */
    public function logSystemConfigUpdated(array $changes): AuditLog
    {
        return $this->log(
            action: 'system.config.updated',
            newValues: $changes,
            description: Auth::user()?->full_name . ' updated system configuration: ' . implode(', ', array_keys($changes))
        );
    }

    /**
     * Get recent audit logs
     */
    public function getRecentLogs(int $limit = 50, string $action = null)
    {
        $query = AuditLog::with('user')->latest();

        if ($action) {
            $query->where('action', $action);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get paginated audit logs
     */
    public function getPaginatedLogs(int $perPage = 15, array $filters = [])
    {
        $query = AuditLog::with('user')->latest();

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Export audit logs to array (for CSV/JSON export)
     */
    public function exportLogs(array $filters = []): array
    {
        $query = AuditLog::with('user');

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        return $query->latest()->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'timestamp' => $log->created_at->toDateTimeString(),
                'user' => $log->user?->full_name ?? 'System',
                'action' => $log->action_label,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'description' => $log->formatted_description,
                'ip_address' => $log->ip_address,
            ];
        })->toArray();
    }
}
