<?php

namespace App\Services;

use App\Models\User;
use App\Models\Group;
use App\Models\AuditLog;
use App\Models\Warning;
use App\Models\BlacklistRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdvancedSearchService
{
    /**
     * Advanced user search with filtering, sorting, and pagination
     * 
     * @param Request $request
     * @param bool $isAdmin
     * @param Collection|null $allowedGroupIds
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function searchUsers(Request $request, bool $isAdmin = false, ?Collection $allowedGroupIds = null)
    {
        $query = User::with(['role', 'group']);

        // Apply group restriction for Group Admins
        if (!$isAdmin && $allowedGroupIds) {
            $query->whereIn('group_id', $allowedGroupIds);
        }

        // Search by text (name or email)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by account status
        if ($request->filled('account_status')) {
            $status = $request->input('account_status');
            if (is_array($status)) {
                $query->whereIn('account_status', $status);
            } else {
                $query->where('account_status', $status);
            }
        }

        // Filter by role
        if ($request->filled('role_id')) {
            $roleId = $request->input('role_id');
            if (is_array($roleId)) {
                $query->whereIn('role_id', $roleId);
            } else {
                $query->where('role_id', $roleId);
            }
        }

        // Filter by group
        if ($request->filled('group_id')) {
            $groupId = $request->input('group_id');
            if (is_array($groupId)) {
                $query->whereIn('group_id', $groupId);
            } else {
                $query->where('group_id', $groupId);
            }
        }

        // Filter by registration date range
        if ($request->filled('registered_from')) {
            $query->where('created_at', '>=', $request->input('registered_from'));
        }
        if ($request->filled('registered_to')) {
            $query->where('created_at', '<=', $request->input('registered_to'));
        }

        // Filter by last active date range
        if ($request->filled('last_active_from')) {
            $query->where('last_active_at', '>=', $request->input('last_active_from'));
        }
        if ($request->filled('last_active_to')) {
            $query->where('last_active_at', '<=', $request->input('last_active_to'));
        }

        // Filter by email verification status
        if ($request->filled('email_verified')) {
            if ($request->input('email_verified') === 'true') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->input('email_verified') === 'false') {
                $query->whereNull('email_verified_at');
            }
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['full_name', 'email', 'created_at', 'last_active_at', 'account_status', 'role_id', 'group_id'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100); // Max 100 per page

        return $query->paginate($perPage);
    }

    /**
     * Advanced group search with filtering, sorting, and pagination
     * 
     * @param Request $request
     * @param bool $isAdmin
     * @param Collection|null $allowedGroupIds
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function searchGroups(Request $request, bool $isAdmin = false, ?Collection $allowedGroupIds = null)
    {
        $query = Group::withCount('users')
            ->with('createdBy');

        // Apply group restriction for Group Admins
        if (!$isAdmin && $allowedGroupIds) {
            $query->whereIn('id', $allowedGroupIds);
        }

        // Search by text (name or description)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $q) use ($search) {
                $q->where('group_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by creation date range
        if ($request->filled('created_from')) {
            $query->where('created_at', '>=', $request->input('created_from'));
        }
        if ($request->filled('created_to')) {
            $query->where('created_at', '<=', $request->input('created_to'));
        }

        // Filter by member count range
        if ($request->filled('min_members')) {
            $query->having('users_count', '>=', $request->input('min_members'));
        }
        if ($request->filled('max_members')) {
            $query->having('users_count', '<=', $request->input('max_members'));
        }

        // Filter by creator
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->input('created_by'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['group_name', 'created_at', 'users_count'];
        if (in_array($sortBy, $allowedSorts)) {
            if ($sortBy === 'users_count') {
                $query->orderByRaw("users_count {$sortOrder}");
            } else {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        return $query->paginate($perPage);
    }

    /**
     * Advanced audit log search with filtering, sorting, and pagination
     * 
     * @param Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function searchAuditLogs(Request $request)
    {
        $query = AuditLog::with('user');

        // Search by description
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        // Filter by action type
        if ($request->filled('action')) {
            $action = $request->input('action');
            if (is_array($action)) {
                $query->whereIn('action', $action);
            } else {
                $query->where('action', $action);
            }
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by target type
        if ($request->filled('target_type')) {
            $query->where('target_type', $request->input('target_type'));
        }

        // Filter by IP address
        if ($request->filled('ip_address')) {
            $query->where('ip_address', $request->input('ip_address'));
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['created_at', 'action', 'user_id'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->input('per_page', 20);
        $perPage = min($perPage, 100);

        return $query->paginate($perPage);
    }

    /**
     * Advanced warning search with filtering, sorting, and pagination
     * 
     * @param Request $request
     * @param bool $isAdmin
     * @param Collection|null $allowedGroupIds
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function searchWarnings(Request $request, bool $isAdmin = false, ?Collection $allowedGroupIds = null)
    {
        $query = Warning::with(['user', 'createdByUser']);

        // Apply group restriction for Group Admins
        if (!$isAdmin && $allowedGroupIds) {
            $query->whereHas('user', function (Builder $q) use ($allowedGroupIds) {
                $q->whereIn('group_id', $allowedGroupIds);
            });
        }

        // Search by reason
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $q) use ($search) {
                $q->where('reason', 'like', "%{$search}%");
            });
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by acknowledgment status
        if ($request->filled('is_acknowledged')) {
            $query->where('is_acknowledged', $request->boolean('is_acknowledged'));
        }

        // Filter by resolution status
        if ($request->filled('is_resolved')) {
            $query->where('is_resolved', $request->boolean('is_resolved'));
        }

        // Filter by warning number
        if ($request->filled('warning_number')) {
            $query->where('warning_number', $request->input('warning_number'));
        }

        // Filter by date range
        if ($request->filled('issued_from')) {
            $query->where('created_at', '>=', $request->input('issued_from'));
        }
        if ($request->filled('issued_to')) {
            $query->where('created_at', '<=', $request->input('issued_to'));
        }

        // Filter by deadline range
        if ($request->filled('deadline_from')) {
            $query->where('response_deadline', '>=', $request->input('deadline_from'));
        }
        if ($request->filled('deadline_to')) {
            $query->where('response_deadline', '<=', $request->input('deadline_to'));
        }

        // Filter overdue warnings
        if ($request->filled('overdue') && $request->boolean('overdue')) {
            $query->where('is_acknowledged', false)
                  ->where('response_deadline', '<', now());
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['created_at', 'warning_number', 'response_deadline', 'is_acknowledged', 'is_resolved'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        return $query->paginate($perPage);
    }

    /**
     * Get available sort options for a model
     * 
     * @param string $model
     * @return array
     */
    public function getSortOptions(string $model): array
    {
        $options = [
            'users' => [
                'full_name' => 'Name',
                'email' => 'Email',
                'created_at' => 'Registration Date',
                'last_active_at' => 'Last Active',
                'account_status' => 'Status',
            ],
            'groups' => [
                'group_name' => 'Name',
                'created_at' => 'Creation Date',
                'users_count' => 'Member Count',
            ],
            'audit_logs' => [
                'created_at' => 'Date',
                'action' => 'Action',
                'user_id' => 'User',
            ],
            'warnings' => [
                'created_at' => 'Issue Date',
                'warning_number' => 'Warning Number',
                'response_deadline' => 'Response Deadline',
            ],
        ];

        return $options[$model] ?? [];
    }

    /**
     * Get available filter options for a model
     * 
     * @param string $model
     * @return array
     */
    public function getFilterOptions(string $model): array
    {
        $options = [
            'users' => [
                'account_status' => ['active', 'warned', 'blacklisted'],
                'email_verified' => ['true', 'false'],
            ],
            'groups' => [],
            'audit_logs' => [
                'actions' => $this->getAuditActionTypes(),
            ],
            'warnings' => [
                'is_acknowledged' => ['true', 'false'],
                'is_resolved' => ['true', 'false'],
            ],
        ];

        return $options[$model] ?? [];
    }

    /**
     * Get audit action type labels
     * 
     * @return array
     */
    private function getAuditActionTypes(): array
    {
        return [
            'user_created' => 'User Created',
            'user_updated' => 'User Updated',
            'user_deleted' => 'User Deleted',
            'role_changed' => 'Role Changed',
            'blacklist_created' => 'Blacklist Created',
            'blacklist_lifted' => 'Blacklist Lifted',
            'warning_issued' => 'Warning Issued',
            'group_created' => 'Group Created',
            'group_updated' => 'Group Updated',
            'group_deleted' => 'Group Deleted',
            'config_updated' => 'Config Updated',
        ];
    }
}
