<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\BlacklistRecord;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AdminUserController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * GET /api/v1/admin/users
     * List all users (with role-based filtering)
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

        $query = User::query();

        // Role-based filtering: Group Admins see only users in their groups
        if ($currentUser->isGroupAdmin()) {
            $adminGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
            $query->whereIn('group_id', $adminGroupIds);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by account status
        if ($request->filled('account_status')) {
            $query->where('account_status', $request->input('account_status'));
        }

        // Filter by role
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->input('role_id'));
        }

        // Filter by group
        if ($request->filled('group_id')) {
            $query->where('group_id', $request->input('group_id'));
        }

        // Eager load relationships
        $users = $query->with(['role', 'group'])
                       ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/users/{userId}
     * Get specific user details
     */
    public function show($userId)
    {
        $currentUser = auth()->user();
        $user = User::with(['role', 'group', 'warnings', 'blacklistRecords'])->findOrFail($userId);

        // Authorization check
        if (!Gate::allows('view', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You cannot view this user.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * POST /api/v1/admin/users/{userId}/change-role
     * Change user role (System Admin only)
     */
    public function changeRole(Request $request, $userId)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($userId);

        // Authorization check
        if (!Gate::allows('changeRole', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can change user roles'
            ], 403);
        }

        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $oldRoleId = $user->role_id;
        $newRoleId = $validated['role_id'];

        // Prevent downgrading the last System Administrator
        $systemAdminRole = Role::where('role_name', 'System Administrator')->first();
        $adminCount = User::where('role_id', $systemAdminRole->id)->count();

        if ($user->role_id === $systemAdminRole->id && $adminCount === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot downgrade the last System Administrator account'
            ], 400);
        }

        $user->update(['role_id' => $newRoleId]);

        // Audit log
        $this->auditLogService->logUserRoleChange($user, $oldRoleId, $newRoleId);

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => $user->load('role'),
        ]);
    }

    /**
     * POST /api/v1/admin/users/{userId}/lift-blacklist
     * Lift blacklist from user
     */
    public function liftBlacklist($userId)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($userId);

        // Authorization check
        if (!Gate::allows('liftBlacklist', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to lift blacklist for this user'
            ], 403);
        }

        // Find active blacklist record
        $blacklistRecord = BlacklistRecord::where('user_id', $userId)
                                          ->whereNull('lifted_at')
                                          ->first();

        if ($blacklistRecord) {
            $blacklistRecord->update([
                'lifted_at' => now(),
                'lifted_by' => Auth::id(),
            ]);
        }

        $user->update(['account_status' => 'active']);

        // Audit log
        $this->auditLogService->logBlacklistLifted($user);

        return response()->json([
            'success' => true,
            'message' => 'Blacklist lifted successfully',
            'data' => $user,
        ]);
    }

    /**
     * POST /api/v1/admin/users/{userId}/warn
     * Issue warning to user
     */
    public function warn(Request $request, $userId)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($userId);

        // Authorization check
        if (!Gate::allows('warn', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to warn this user'
            ], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'response_deadline' => 'required|date|after:now',
        ]);

        $warning = $user->warnings()->create([
            'reason' => $validated['reason'],
            'response_deadline' => $validated['response_deadline'],
            'created_by' => Auth::id(),
        ]);

        $user->update(['account_status' => 'warned']);

        // Audit log
        $this->auditLogService->logUserWarned($user, $validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Warning issued successfully',
            'data' => $warning,
        ], 201);
    }
}
