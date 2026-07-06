<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * GET /api/v1/admin/audit-logs
     * List audit logs with filters
     */
    public function index(Request $request)
    {
        $currentUser = auth()->user();

        // Authorization check
        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $filters = [
            'action' => $request->input('action'),
            'user_id' => $request->input('user_id'),
            'target_type' => $request->input('target_type'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        // Group admins only see logs related to users in their administered groups
        if ($currentUser->isGroupAdmin()) {
            $adminGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
            $filters['group_ids'] = $adminGroupIds->toArray();
        }

        $logs = $this->auditLogService->getPaginatedLogs(
            $request->input('per_page', 20),
            $filters
        );

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/audit-logs/{logId}
     * Get specific audit log details
     */
    public function show($logId)
    {
        // Authorization check
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $log = AuditLog::with('user')->findOrFail($logId);

        return response()->json([
            'success' => true,
            'data' => $log,
        ]);
    }

    /**
     * GET /api/v1/admin/audit-logs/export/{format}
     * Export audit logs (JSON or CSV)
     */
    public function export(Request $request, $format = 'json')
    {
        // Authorization check
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $filters = [
            'action' => $request->input('action'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        // Group admins only see logs related to users in their administered groups
        if (auth()->user()->isGroupAdmin()) {
            $adminGroupIds = auth()->user()->administeredGroups()->pluck('groups.id');
            $filters['group_ids'] = $adminGroupIds->toArray();
        }

        $logs = $this->auditLogService->exportLogs($filters);

        if ($format === 'csv') {
            // For API, we'll return JSON with CSV data
            // In a real application, you might want to return actual CSV or provide a download link
            return response()->json([
                'success' => true,
                'format' => 'csv',
                'data' => $logs,
                'count' => count($logs),
            ]);
        }

        return response()->json([
            'success' => true,
            'format' => 'json',
            'data' => $logs,
            'count' => count($logs),
        ]);
    }

    /**
     * GET /api/v1/admin/audit-logs/actions
     * Get list of unique action types
     */
    public function getActions()
    {
        // Authorization check
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $actions = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->map(function ($action) {
                return [
                    'value' => $action,
                    'label' => $this->getActionLabel($action),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $actions,
        ]);
    }

    /**
     * Get action label
     */
    protected function getActionLabel(string $action): string
    {
        $labels = [
            'user.role.changed' => 'User Role Changed',
            'user.group.changed' => 'User Group Changed',
            'user.blacklisted' => 'User Blacklisted',
            'user.blacklist.lifted' => 'User Blacklist Lifted',
            'user.warned' => 'User Warned',
            'user.activated' => 'User Activated',
            'user.deleted' => 'User Deleted',
            'group.created' => 'Group Created',
            'group.updated' => 'Group Updated',
            'group.deleted' => 'Group Deleted',
            'group.member.added' => 'Group Member Added',
            'group.member.removed' => 'Group Member Removed',
            'system.config.updated' => 'System Configuration Updated',
            'admin.ip.added' => 'Admin IP Added',
            'admin.ip.removed' => 'Admin IP Removed',
        ];

        return $labels[$action] ?? ucfirst(str_replace('.', ' ', $action));
    }
}
