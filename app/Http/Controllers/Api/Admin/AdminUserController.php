<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlacklistRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

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
        if (! $currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
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
        if (! Gate::allows('view', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You cannot view this user.',
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
        if (! Gate::allows('changeRole', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can change user roles',
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
                'message' => 'Cannot downgrade the last System Administrator account',
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
        if (! Gate::allows('liftBlacklist', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to lift blacklist for this user',
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
        if (! Gate::allows('warn', $user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to warn this user',
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

    /**
     * POST /api/v1/admin/users
     * Create a new user. System Admin only.
     */
    public function store(Request $request)
    {
        if (! auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can create users.',
            ], 403);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_name' => 'required|string|exists:roles,role_name',
            'group_id' => 'nullable|exists:groups,id',
        ]);

        $role = Role::where('role_name', $validated['role_name'])->firstOrFail();

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $role->id,
            'group_id' => $validated['group_id'],
            'account_status' => 'active',
        ]);

        $this->auditLogService->log('user_created', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $role->role_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created.',
            'data' => [
                'user' => $user->load('role', 'group'),
            ],
        ], 201);
    }

    /**
     * PUT /api/v1/admin/users/{userId}
     * Update an existing user. System Admin for all fields; Group Admin
     * can edit users in their groups but cannot change roles.
     */
    public function update(Request $request, $userId)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($userId);

        // Group-admin scope: can only edit users in their own groups
        if ($currentUser->isGroupAdmin()) {
            $adminGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
            if (! in_array($user->group_id, $adminGroupIds->toArray())) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot edit users outside your groups.',
                ], 403);
            }
        }

        // Group admins cannot change roles
        if ($currentUser->isGroupAdmin() && $request->has('role_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Group Administrators cannot change user roles.',
            ], 403);
        }

        $rules = [
            'full_name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'group_id' => 'sometimes|exists:groups,id',
        ];

        // Only System Admin can change role_id
        if ($currentUser->isSystemAdmin() && $request->has('role_id')) {
            $rules['role_id'] = 'exists:roles,id';
        }

        $validated = $request->validate($rules);

        $user->update($validated);

        $this->auditLogService->log('user_updated', [
            'user_id' => $user->id,
            'updated_fields' => array_keys($validated),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User updated.',
            'data' => [
                'user' => $user->fresh()->load('role', 'group'),
            ],
        ]);
    }

    /**
     * DELETE /api/v1/admin/users/{userId}
     * Delete a user. System Admin only. Cannot delete yourself.
     */
    public function destroy($userId)
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can delete users.',
            ], 403);
        }

        if ((int) $userId === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $user = User::findOrFail($userId);
        $user->delete();

        $this->auditLogService->log('user_deleted', [
            'user_id' => $userId,
            'email' => $user->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User deleted.',
        ]);
    }

    /**
     * POST /api/v1/admin/users/{userId}/reset-password
     * Send a password reset link. System Admin only.
     */
    public function resetPassword($userId)
    {
        if (! auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can reset passwords.',
            ], 403);
        }

        $user = User::findOrFail($userId);

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->auditLogService->log('password_reset', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unable to send reset link.',
        ], 500);
    }
}
