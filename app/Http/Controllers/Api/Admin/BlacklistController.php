<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlacklistRecord;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlacklistController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * W5: List all blacklist records (admin-only, group-scoped).
     *
     * GET /api/v1/admin/blacklist-records
     *
     * System Admin: sees all records.
     * Group Admin: sees records for users in their administered groups.
     * Supports filtering by user_id, is_active (still active blacklist).
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();

        $query = BlacklistRecord::with(['user', 'liftedBy']);

        // Group Admin scope: only records for users in their groups
        if ($currentUser->isGroupAdmin()) {
            $adminGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
            $query->whereHas('user', function ($q) use ($adminGroupIds) {
                $q->whereIn('group_id', $adminGroupIds);
            });
        }

        // Filter by user_id
        if ($request->filled('user_id')) {
            $targetUser = User::findOrFail($request->input('user_id'));

            // Group Admin can only view records for users in their groups
            if ($currentUser->isGroupAdmin() && !$currentUser->canAdminUser($targetUser)) {
                return response()->json([
                    'message' => 'You do not have permission to view blacklist records for this user.',
                ], 403);
            }

            $query->where('user_id', $targetUser->id);
        }

        // Filter by active status (null lifted_at = still active)
        if ($request->filled('is_active')) {
            if ($request->boolean('is_active')) {
                $query->whereNull('lifted_at');
            } else {
                $query->whereNotNull('lifted_at');
            }
        }

        $records = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'pagination' => [
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
            ],
        ]);
    }

    /**
     * W6: Blacklist a user (admin-only, group-scoped).
     *
     * POST /api/v1/admin/users/{userId}/blacklist
     *
     * Creates a blacklist record and updates user account_status.
     * Optional duration_days for time-limited blacklist (null = permanent).
     */
    public function store(Request $request, int $userId)
    {
        $currentUser = $request->user();
        $targetUser = User::findOrFail($userId);

        // Group Admin scope: can only blacklist users in their groups
        if ($currentUser->isGroupAdmin() && !$currentUser->canAdminUser($targetUser)) {
            return response()->json([
                'message' => 'You do not have permission to blacklist this user.',
            ], 403);
        }

        // Check if user is already blacklisted (active record)
        $existingActive = BlacklistRecord::where('user_id', $targetUser->id)
            ->whereNull('lifted_at')
            ->exists();

        if ($existingActive) {
            return response()->json([
                'message' => 'User is already blacklisted.',
            ], 409);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        // Calculate expires_at if duration provided, otherwise null (permanent)
        $expiresAt = null;
        if (!empty($validated['duration_days'])) {
            $expiresAt = now()->addDays($validated['duration_days']);
        }

        // Create blacklist record
        $record = BlacklistRecord::create([
            'user_id' => $targetUser->id,
            'reason' => $validated['reason'],
            'expires_at' => $expiresAt,
        ]);

        // Update user status
        $targetUser->update(['account_status' => 'blacklisted']);

        // Audit log
        $this->auditLogService->logUserBlacklisted($targetUser, $validated['reason'], $expiresAt);

        $record->load(['user', 'liftedBy']);

        return response()->json([
            'success' => true,
            'message' => 'User has been blacklisted successfully.',
            'data' => [
                'blacklist_record' => $record,
                'expires_at' => $expiresAt,
                'is_permanent' => $expiresAt === null,
            ],
        ], 201);
    }

    /**
     * W7: Lift a blacklist record (admin-only, group-scoped).
     *
     * POST /api/v1/admin/blacklist-records/{recordId}/lift
     *
     * Sets lifted_at and lifted_by on the record.
     * Reverts user account_status to 'active' if no other active blacklists remain.
     */
    public function lift(Request $request, int $recordId)
    {
        $currentUser = $request->user();

        $record = BlacklistRecord::with('user')->findOrFail($recordId);

        // Group Admin scope check
        if ($currentUser->isGroupAdmin() && !$currentUser->canAdminUser($record->user)) {
            return response()->json([
                'message' => 'You do not have permission to lift this blacklist.',
            ], 403);
        }

        // Already lifted?
        if ($record->lifted_at !== null) {
            return response()->json([
                'message' => 'This blacklist record has already been lifted.',
            ], 409);
        }

        // Lift the blacklist
        $record->update([
            'lifted_at' => now(),
            'lifted_by' => Auth::id(),
        ]);

        // Check if user has any other active blacklist records
        $user = $record->user;
        $activeBlacklists = BlacklistRecord::where('user_id', $user->id)
            ->whereNull('lifted_at')
            ->count();

        // If no other active blacklists, revert user status to 'active'
        if ($activeBlacklists === 0) {
            $user->update(['account_status' => 'active']);
        }

        // Audit log
        $this->auditLogService->logBlacklistLifted($user);

        $record->load(['user', 'liftedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Blacklist lifted successfully.',
            'data' => [
                'blacklist_record' => $record,
                'remaining_active_blacklists' => $activeBlacklists,
                'user_status' => $user->fresh()->account_status,
            ],
        ]);
    }
}
