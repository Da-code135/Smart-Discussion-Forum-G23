<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlacklistRecord;
use App\Models\User;
use App\Models\Warning;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WarningController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * W1: List all warnings (admin-only, group-scoped).
     *
     * GET /api/v1/admin/warnings
     *
     * System Admin: sees all warnings.
     * Group Admin: sees warnings for users in their administered groups.
     * Supports filtering by user_id, is_resolved, is_acknowledged.
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();

        $query = Warning::with(['user', 'createdBy']);

        // Group Admin scope: only warnings for users in their groups
        if ($currentUser->isGroupAdmin()) {
            $adminGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
            $query->whereHas('user', function ($q) use ($adminGroupIds) {
                $q->whereIn('group_id', $adminGroupIds);
            });
        }

        // Filter by user_id
        if ($request->filled('user_id')) {
            $targetUser = User::findOrFail($request->input('user_id'));

            // Group Admin can only view warnings for users in their groups
            if ($currentUser->isGroupAdmin() && ! $currentUser->canAdminUser($targetUser)) {
                return response()->json([
                    'message' => 'You do not have permission to view warnings for this user.',
                ], 403);
            }

            $query->where('user_id', $targetUser->id);
        }

        // Filter by resolved status
        if ($request->filled('is_resolved')) {
            $query->where('is_resolved', $request->boolean('is_resolved'));
        }

        // Filter by acknowledged status
        if ($request->filled('is_acknowledged')) {
            $query->where('is_acknowledged', $request->boolean('is_acknowledged'));
        }

        $warnings = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $warnings->items(),
            'pagination' => [
                'total' => $warnings->total(),
                'per_page' => $warnings->perPage(),
                'current_page' => $warnings->currentPage(),
                'last_page' => $warnings->lastPage(),
                'from' => $warnings->firstItem(),
                'to' => $warnings->lastItem(),
            ],
        ]);
    }

    /**
     * W2: Show a specific warning (admin-only, group-scoped).
     *
     * GET /api/v1/admin/warnings/{warningId}
     */
    public function show(Request $request, int $warningId)
    {
        $currentUser = $request->user();

        $warning = Warning::with(['user', 'createdBy'])->findOrFail($warningId);

        // Group Admin scope check
        if ($currentUser->isGroupAdmin() && ! $currentUser->canAdminUser($warning->user)) {
            return response()->json([
                'message' => 'You do not have permission to view this warning.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $warning,
        ]);
    }

    /**
     * W3: Issue a warning to a user (admin-only, group-scoped).
     *
     * POST /api/v1/admin/users/{userId}/warnings
     *
     * Automatically computes warning_number (escalating 1→2→3).
     * On 3rd warning, auto-blacklists the user.
     * Updates user account_status to 'warned' (or 'blacklisted' on 3rd).
     */
    public function store(Request $request, int $userId)
    {
        $currentUser = $request->user();
        $targetUser = User::findOrFail($userId);

        // Group Admin scope: can only warn users in their groups
        if ($currentUser->isGroupAdmin() && ! $currentUser->canAdminUser($targetUser)) {
            return response()->json([
                'message' => 'You do not have permission to warn this user.',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'response_deadline' => 'required|date|after:now',
        ]);

        // Compute next warning number based on existing warnings
        $existingCount = $targetUser->warnings()->count();
        $warningNumber = $existingCount + 1;

        // Create the warning
        $warning = $targetUser->warnings()->create([
            'warning_number' => $warningNumber,
            'reason' => $validated['reason'],
            'response_deadline' => $validated['response_deadline'],
            'created_by' => Auth::id(),
        ]);

        // If 3rd warning → auto-blacklist
        if ($warningNumber >= 3) {
            $blacklistRecord = BlacklistRecord::create([
                'user_id' => $targetUser->id,
                'reason' => 'Automatic blacklist: 3 warnings issued. Last reason: '.$validated['reason'],
                'expires_at' => null, // Permanent until admin lifts
            ]);

            $targetUser->update(['account_status' => 'blacklisted']);

            $this->auditLogService->logUserBlacklisted(
                $targetUser,
                'Automatic blacklist: '.$warningNumber.' warnings issued',
                null
            );
        } else {
            $targetUser->update(['account_status' => 'warned']);
        }

        // Audit log for the warning
        $this->auditLogService->logUserWarned($targetUser, $validated['reason']);

        $warning->load(['user', 'createdBy']);

        return response()->json([
            'success' => true,
            'message' => $warningNumber >= 3
                ? "Warning #{$warningNumber} issued. User has been automatically blacklisted (3 warnings reached)."
                : "Warning #{$warningNumber} issued successfully.",
            'data' => [
                'warning' => $warning,
                'warning_number' => $warningNumber,
                'auto_blacklisted' => $warningNumber >= 3,
            ],
        ], 201);
    }

    /**
     * W4: Resolve a warning (admin-only, group-scoped).
     *
     * POST /api/v1/admin/warnings/{warningId}/resolve
     *
     * Marks warning as resolved. If no unresolved warnings remain,
     * reverts user account_status from 'warned' to 'active'.
     */
    public function resolve(Request $request, int $warningId)
    {
        $currentUser = $request->user();

        $warning = Warning::with('user')->findOrFail($warningId);

        // Group Admin scope check
        if ($currentUser->isGroupAdmin() && ! $currentUser->canAdminUser($warning->user)) {
            return response()->json([
                'message' => 'You do not have permission to resolve this warning.',
            ], 403);
        }

        // Already resolved?
        if ($warning->is_resolved) {
            return response()->json([
                'message' => 'This warning is already resolved.',
            ], 409);
        }

        // Mark as resolved
        $warning->update([
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);

        // Check if user has any remaining unresolved warnings
        $user = $warning->user;
        $unresolvedCount = $user->warnings()->where('is_resolved', false)->count();

        // If no unresolved warnings remain, revert status from 'warned' to 'active'
        if ($unresolvedCount === 0 && $user->account_status === 'warned') {
            $user->update(['account_status' => 'active']);
        }

        // Audit log
        $this->auditLogService->logWarningResolved($warning);

        $warning->load(['user', 'createdBy']);

        return response()->json([
            'success' => true,
            'message' => 'Warning resolved successfully.',
            'data' => [
                'warning' => $warning,
                'remaining_unresolved' => $unresolvedCount,
                'user_status' => $user->fresh()->account_status,
            ],
        ]);
    }
}
