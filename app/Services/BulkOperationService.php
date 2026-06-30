<?php

namespace App\Services;

use App\Models\User;
use App\Models\Group;
use App\Models\Role;
use App\Models\BlacklistRecord;
use App\Models\Warning;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class BulkOperationService
{
    protected AuditLogService $auditLogService;
    protected CacheService $cacheService;

    public function __construct(AuditLogService $auditLogService, CacheService $cacheService)
    {
        $this->auditLogService = $auditLogService;
        $this->cacheService = $cacheService;
    }

    /**
     * Bulk change user roles
     * 
     * @param array $userIds
     * @param int $newRoleId
     * @param int|null $performedBy
     * @return array
     */
    public function bulkChangeRoles(array $userIds, int $newRoleId, ?int $performedBy = null): array
    {
        $performedBy = $performedBy ?? Auth::id();
        $results = ['success' => [], 'failed' => [], 'skipped' => []];
        
        $newRole = Role::find($newRoleId);
        if (!$newRole) {
            return ['error' => 'Invalid role ID'];
        }

        // Check if trying to downgrade from System Administrator
        $systemAdminRole = Role::where('role_name', 'System Administrator')->first();
        $adminCount = User::where('role_id', $systemAdminRole->id)->count();

        DB::transaction(function () use ($userIds, $newRoleId, $performedBy, $systemAdminRole, &$adminCount, &$results) {
            $users = User::whereIn('id', $userIds)->get();
            
            foreach ($users as $user) {
                try {
                    // Prevent downgrading last System Admin
                    if ($user->role_id === $systemAdminRole->id && $adminCount === 1 && $newRoleId !== $systemAdminRole->id) {
                        $results['skipped'][] = [
                            'user_id' => $user->id,
                            'reason' => 'Cannot downgrade the last System Administrator'
                        ];
                        continue;
                    }

                    $oldRoleId = $user->role_id;
                    
                    // Update admin count
                    if ($user->role_id === $systemAdminRole->id && $newRoleId !== $systemAdminRole->id) {
                        $adminCount--;
                    } elseif ($user->role_id !== $systemAdminRole->id && $newRoleId === $systemAdminRole->id) {
                        $adminCount++;
                    }

                    $user->update(['role_id' => $newRoleId]);
                    
                    // Log the change
                    $this->auditLogService->logUserRoleChange($user, $oldRoleId, $newRoleId, $performedBy);
                    
                    $results['success'][] = $user->id;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        // Clear caches
        $this->cacheService->clearUserCaches();

        return $results;
    }

    /**
     * Bulk change user account status
     * 
     * @param array $userIds
     * @param string $newStatus
     * @param int|null $performedBy
     * @return array
     */
    public function bulkChangeStatus(array $userIds, string $newStatus, ?int $performedBy = null): array
    {
        $performedBy = $performedBy ?? Auth::id();
        $results = ['success' => [], 'failed' => [], 'skipped' => []];

        if (!in_array($newStatus, ['active', 'warned', 'blacklisted'])) {
            return ['error' => 'Invalid status'];
        }

        DB::transaction(function () use ($userIds, $newStatus, $performedBy, &$results) {
            $users = User::whereIn('id', $userIds)->get();
            
            foreach ($users as $user) {
                try {
                    $oldStatus = $user->account_status;
                    $user->update(['account_status' => $newStatus]);
                    
                    // Log the change
                    $this->auditLogService->log(
                        'bulk_status_change',
                        $user,
                        ['account_status' => $oldStatus],
                        ['account_status' => $newStatus],
                        "Bulk status change: {$oldStatus} → {$newStatus}",
                        $performedBy
                    );
                    
                    $results['success'][] = $user->id;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        // Clear caches
        $this->cacheService->clearUserCaches();

        return $results;
    }

    /**
     * Bulk assign users to groups
     * 
     * @param array $userIds
     * @param int $groupId
     * @param int|null $performedBy
     * @return array
     */
    public function bulkAssignToGroup(array $userIds, int $groupId, ?int $performedBy = null): array
    {
        $performedBy = $performedBy ?? Auth::id();
        $results = ['success' => [], 'failed' => [], 'skipped' => []];

        $group = Group::find($groupId);
        if (!$group) {
            return ['error' => 'Invalid group ID'];
        }

        DB::transaction(function () use ($userIds, $groupId, $performedBy, &$results) {
            $users = User::whereIn('id', $userIds)->get();
            
            foreach ($users as $user) {
                try {
                    $oldGroupId = $user->group_id;
                    $user->update(['group_id' => $groupId]);
                    
                    // Log the change
                    $this->auditLogService->log(
                        'bulk_group_assignment',
                        $user,
                        ['group_id' => $oldGroupId],
                        ['group_id' => $groupId],
                        "Bulk group assignment: moved to group {$groupId}",
                        $performedBy
                    );
                    
                    $results['success'][] = $user->id;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        // Clear caches
        $this->cacheService->clearGroupCaches($groupId);
        $this->cacheService->clearUserCaches();

        return $results;
    }

    /**
     * Bulk blacklist users
     * 
     * @param array $userIds
     * @param string $reason
     * @param int|null $durationDays
     * @param int|null $performedBy
     * @return array
     */
    public function bulkBlacklist(array $userIds, string $reason, ?int $durationDays = null, ?int $performedBy = null): array
    {
        $performedBy = $performedBy ?? Auth::id();
        $results = ['success' => [], 'failed' => [], 'skipped' => []];

        DB::transaction(function () use ($userIds, $reason, $durationDays, $performedBy, &$results) {
            $users = User::whereIn('id', $userIds)->get();
            
            foreach ($users as $user) {
                try {
                    // Check if already blacklisted
                    $existingBlacklist = BlacklistRecord::where('user_id', $user->id)
                        ->whereNull('lifted_at')
                        ->exists();
                    
                    if ($existingBlacklist) {
                        $results['skipped'][] = [
                            'user_id' => $user->id,
                            'reason' => 'User is already blacklisted'
                        ];
                        continue;
                    }

                    // Create blacklist record
                    BlacklistRecord::create([
                        'user_id' => $user->id,
                        'reason' => $reason,
                        'blacklisted_at' => now(),
                        'expires_at' => $durationDays ? now()->addDays($durationDays) : null,
                    ]);

                    // Update user status
                    $user->update(['account_status' => 'blacklisted']);
                    
                    // Log the action
                    $this->auditLogService->logBlacklistCreated($user, $reason, $performedBy);
                    
                    $results['success'][] = $user->id;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        // Clear caches
        $this->cacheService->clearUserCaches();

        return $results;
    }

    /**
     * Bulk lift blacklists
     * 
     * @param array $userIds
     * @param int|null $performedBy
     * @return array
     */
    public function bulkLiftBlacklist(array $userIds, ?int $performedBy = null): array
    {
        $performedBy = $performedBy ?? Auth::id();
        $results = ['success' => [], 'failed' => [], 'skipped' => []];

        DB::transaction(function () use ($userIds, $performedBy, &$results) {
            $users = User::whereIn('id', $userIds)->get();
            
            foreach ($users as $user) {
                try {
                    $blacklistRecord = BlacklistRecord::where('user_id', $user->id)
                        ->whereNull('lifted_at')
                        ->first();
                    
                    if (!$blacklistRecord) {
                        $results['skipped'][] = [
                            'user_id' => $user->id,
                            'reason' => 'User is not blacklisted'
                        ];
                        continue;
                    }

                    $blacklistRecord->update([
                        'lifted_at' => now(),
                        'lifted_by' => $performedBy,
                    ]);

                    $user->update(['account_status' => 'active']);
                    
                    // Log the action
                    $this->auditLogService->logBlacklistLifted($user, $performedBy);
                    
                    $results['success'][] = $user->id;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        // Clear caches
        $this->cacheService->clearUserCaches();

        return $results;
    }

    /**
     * Bulk assign group admins
     * 
     * @param array $userIds
     * @param int $groupId
     * @param int|null $performedBy
     * @return array
     */
    public function bulkAssignGroupAdmins(array $userIds, int $groupId, ?int $performedBy = null): array
    {
        $performedBy = $performedBy ?? Auth::id();
        $results = ['success' => [], 'failed' => [], 'skipped' => []];

        $group = Group::find($groupId);
        if (!$group) {
            return ['error' => 'Invalid group ID'];
        }

        DB::transaction(function () use ($userIds, $group, $performedBy, &$results) {
            $users = User::whereIn('id', $userIds)
                ->whereHas('role', function ($q) {
                    $q->where('role_name', 'Group Administrator');
                })
                ->get();
            
            foreach ($users as $user) {
                try {
                    // Check if already admin
                    if ($group->hasAdmin($user)) {
                        $results['skipped'][] = [
                            'user_id' => $user->id,
                            'reason' => 'User is already an admin of this group'
                        ];
                        continue;
                    }

                    $group->addAdmin($user, $performedBy);
                    
                    // Log the action
                    $this->auditLogService->log(
                        'group_admin_assigned',
                        $group,
                        [],
                        ['admin_user_id' => $user->id],
                        "Assigned {$user->full_name} as group admin",
                        $performedBy
                    );
                    
                    $results['success'][] = $user->id;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        // Clear caches
        $this->cacheService->clearGroupCaches($groupId);

        return $results;
    }

    /**
     * Bulk warn users
     * 
     * @param array $userIds
     * @param string $reason
     * @param int $responseDays
     * @param int|null $performedBy
     * @return array
     */
    public function bulkWarnUsers(array $userIds, string $reason, int $responseDays = 7, ?int $performedBy = null): array
    {
        $performedBy = $performedBy ?? Auth::id();
        $results = ['success' => [], 'failed' => [], 'skipped' => []];

        DB::transaction(function () use ($userIds, $reason, $responseDays, $performedBy, &$results) {
            $users = User::whereIn('id', $userIds)->get();
            
            foreach ($users as $user) {
                try {
                    // Get warning number
                    $warningCount = $user->warnings()->count();
                    $warningNumber = $warningCount + 1;

                    // Create warning
                    Warning::create([
                        'user_id' => $user->id,
                        'warning_number' => $warningNumber,
                        'reason' => $reason,
                        'response_deadline' => now()->addDays($responseDays),
                        'created_by' => $performedBy,
                    ]);

                    // Update user status to warned
                    $user->update(['account_status' => 'warned']);
                    
                    // Log the action
                    $this->auditLogService->log(
                        'user_warned',
                        $user,
                        ['account_status' => 'active'],
                        ['account_status' => 'warned', 'warning_number' => $warningNumber],
                        "Issued warning #{$warningNumber}: {$reason}",
                        $performedBy
                    );
                    
                    $results['success'][] = $user->id;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        // Clear caches
        $this->cacheService->clearUserCaches();

        return $results;
    }
}
