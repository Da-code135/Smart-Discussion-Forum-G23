<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\BulkOperationService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BulkOperationController extends Controller
{
    protected BulkOperationService $bulkOperationService;
    protected AuditLogService $auditLogService;

    public function __construct(
        BulkOperationService $bulkOperationService,
        AuditLogService $auditLogService
    ) {
        $this->bulkOperationService = $bulkOperationService;
        $this->auditLogService = $auditLogService;
    }

    /**
     * POST /api/v1/admin/bulk/change-roles
     * Bulk change user roles
     */
    public function changeRoles(Request $request)
    {
        $currentUser = auth()->user();

        // Only System Admins can change roles
        if (!$currentUser->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can perform bulk role changes'
            ], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'integer|exists:users,id',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $results = $this->bulkOperationService->bulkChangeRoles(
            $validated['user_ids'],
            $validated['role_id'],
            Auth::id()
        );

        if (isset($results['error'])) {
            return response()->json([
                'success' => false,
                'message' => $results['error']
            ], 400);
        }

        // Log the bulk operation
        $this->auditLogService->log(
            'bulk_role_change',
            null,
            [],
            [
                'user_ids' => $validated['user_ids'],
                'role_id' => $validated['role_id'],
                'results' => $results,
            ],
            "Bulk role change: " . count($results['success']) . " users updated"
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk role change completed',
            'data' => [
                'total' => count($validated['user_ids']),
                'successful' => count($results['success']),
                'failed' => count($results['failed']),
                'skipped' => count($results['skipped']),
                'details' => $results,
            ]
        ]);
    }

    /**
     * POST /api/v1/admin/bulk/change-status
     * Bulk change user account status
     */
    public function changeStatus(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'integer|exists:users,id',
            'status' => 'required|string|in:active,warned,blacklisted',
        ]);

        $results = $this->bulkOperationService->bulkChangeStatus(
            $validated['user_ids'],
            $validated['status'],
            Auth::id()
        );

        if (isset($results['error'])) {
            return response()->json([
                'success' => false,
                'message' => $results['error']
            ], 400);
        }

        // Log the bulk operation
        $this->auditLogService->log(
            'bulk_status_change',
            null,
            [],
            [
                'user_ids' => $validated['user_ids'],
                'status' => $validated['status'],
                'results' => $results,
            ],
            "Bulk status change: " . count($results['success']) . " users updated"
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk status change completed',
            'data' => [
                'total' => count($validated['user_ids']),
                'successful' => count($results['success']),
                'failed' => count($results['failed']),
                'skipped' => count($results['skipped']),
                'details' => $results,
            ]
        ]);
    }

    /**
     * POST /api/v1/admin/bulk/assign-group
     * Bulk assign users to groups
     */
    public function assignGroup(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'integer|exists:users,id',
            'group_id' => 'required|integer|exists:groups,id',
        ]);

        // Group Admins can only assign to their groups
        if ($currentUser->isGroupAdmin()) {
            $canAssign = $currentUser->canAdminGroup(
                \App\Models\Group::find($validated['group_id'])
            );
            if (!$canAssign) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only assign users to groups you administer'
                ], 403);
            }
        }

        $results = $this->bulkOperationService->bulkAssignToGroup(
            $validated['user_ids'],
            $validated['group_id'],
            Auth::id()
        );

        if (isset($results['error'])) {
            return response()->json([
                'success' => false,
                'message' => $results['error']
            ], 400);
        }

        // Log the bulk operation
        $this->auditLogService->log(
            'bulk_group_assignment',
            null,
            [],
            [
                'user_ids' => $validated['user_ids'],
                'group_id' => $validated['group_id'],
                'results' => $results,
            ],
            "Bulk group assignment: " . count($results['success']) . " users assigned"
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk group assignment completed',
            'data' => [
                'total' => count($validated['user_ids']),
                'successful' => count($results['success']),
                'failed' => count($results['failed']),
                'skipped' => count($results['skipped']),
                'details' => $results,
            ]
        ]);
    }

    /**
     * POST /api/v1/admin/bulk/blacklist
     * Bulk blacklist users
     */
    public function blacklist(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'integer|exists:users,id',
            'reason' => 'required|string|max:500',
            'duration_days' => 'nullable|integer|min:1',
        ]);

        $results = $this->bulkOperationService->bulkBlacklist(
            $validated['user_ids'],
            $validated['reason'],
            $validated['duration_days'] ?? null,
            Auth::id()
        );

        if (isset($results['error'])) {
            return response()->json([
                'success' => false,
                'message' => $results['error']
            ], 400);
        }

        // Log the bulk operation
        $this->auditLogService->log(
            'bulk_blacklist',
            null,
            [],
            [
                'user_ids' => $validated['user_ids'],
                'reason' => $validated['reason'],
                'duration_days' => $validated['duration_days'] ?? null,
                'results' => $results,
            ],
            "Bulk blacklist: " . count($results['success']) . " users blacklisted"
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk blacklist completed',
            'data' => [
                'total' => count($validated['user_ids']),
                'successful' => count($results['success']),
                'failed' => count($results['failed']),
                'skipped' => count($results['skipped']),
                'details' => $results,
            ]
        ]);
    }

    /**
     * POST /api/v1/admin/bulk/lift-blacklist
     * Bulk lift blacklists
     */
    public function liftBlacklist(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $results = $this->bulkOperationService->bulkLiftBlacklist(
            $validated['user_ids'],
            Auth::id()
        );

        if (isset($results['error'])) {
            return response()->json([
                'success' => false,
                'message' => $results['error']
            ], 400);
        }

        // Log the bulk operation
        $this->auditLogService->log(
            'bulk_blacklist_lift',
            null,
            [],
            [
                'user_ids' => $validated['user_ids'],
                'results' => $results,
            ],
            "Bulk blacklist lift: " . count($results['success']) . " users unblacklisted"
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk blacklist lift completed',
            'data' => [
                'total' => count($validated['user_ids']),
                'successful' => count($results['success']),
                'failed' => count($results['failed']),
                'skipped' => count($results['skipped']),
                'details' => $results,
            ]
        ]);
    }

    /**
     * POST /api/v1/admin/bulk/warn
     * Bulk warn users
     */
    public function warn(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'integer|exists:users,id',
            'reason' => 'required|string|max:500',
            'response_days' => 'nullable|integer|min:1|max:30',
        ]);

        $results = $this->bulkOperationService->bulkWarnUsers(
            $validated['user_ids'],
            $validated['reason'],
            $validated['response_days'] ?? 7,
            Auth::id()
        );

        if (isset($results['error'])) {
            return response()->json([
                'success' => false,
                'message' => $results['error']
            ], 400);
        }

        // Log the bulk operation
        $this->auditLogService->log(
            'bulk_warning',
            null,
            [],
            [
                'user_ids' => $validated['user_ids'],
                'reason' => $validated['reason'],
                'results' => $results,
            ],
            "Bulk warning: " . count($results['success']) . " users warned"
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk warning completed',
            'data' => [
                'total' => count($validated['user_ids']),
                'successful' => count($results['success']),
                'failed' => count($results['failed']),
                'skipped' => count($results['skipped']),
                'details' => $results,
            ]
        ]);
    }

    /**
     * POST /api/v1/admin/bulk/assign-group-admins
     * Bulk assign group admins
     */
    public function assignGroupAdmins(Request $request)
    {
        $currentUser = auth()->user();

        // Only System Admins can assign group admins
        if (!$currentUser->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can assign group admins'
            ], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:50',
            'user_ids.*' => 'integer|exists:users,id',
            'group_id' => 'required|integer|exists:groups,id',
        ]);

        $results = $this->bulkOperationService->bulkAssignGroupAdmins(
            $validated['user_ids'],
            $validated['group_id'],
            Auth::id()
        );

        if (isset($results['error'])) {
            return response()->json([
                'success' => false,
                'message' => $results['error']
            ], 400);
        }

        // Log the bulk operation
        $this->auditLogService->log(
            'bulk_group_admin_assign',
            null,
            [],
            [
                'user_ids' => $validated['user_ids'],
                'group_id' => $validated['group_id'],
                'results' => $results,
            ],
            "Bulk group admin assignment: " . count($results['success']) . " admins assigned"
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk group admin assignment completed',
            'data' => [
                'total' => count($validated['user_ids']),
                'successful' => count($results['success']),
                'failed' => count($results['failed']),
                'skipped' => count($results['skipped']),
                'details' => $results,
            ]
        ]);
    }
}
