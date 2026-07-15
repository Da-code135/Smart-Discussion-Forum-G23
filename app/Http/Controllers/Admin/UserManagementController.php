<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlacklistRecord;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Models\Warning;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    // ============================================
    // #88: SHOW USERS TABLE (with role-based filtering)
    // ============================================
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $query = User::query();//this initializes a query builder for the User model, allowing us to build a query to fetch users from the database.

        // Role-based filtering: Group Admins see only users in their groups
        if ($currentUser->isGroupAdmin()) {
            $adminGroupIds = $currentUser->administeredGroups()->pluck('groups.id');//this retrieves the IDs of the groups that the current Group Admin manages. It uses the administeredGroups relationship to get the groups and then plucks their IDs into a collection.
            $query->whereIn('group_id', $adminGroupIds);
        }
        // System Admins see all users (no filter needed)

        // #89: SEARCH FUNCTIONALITY
        if ($request->filled('search')) {//did the admin enter a search term in the search box? If so, we want to filter the users based on that term.
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {//this adds a nested where condition to the query. It allows us to search for users where either the full_name or email contains the search term.
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // #89: FILTER BY ACCOUNT STATUS
        if ($request->filled('account_status')) {
            $query->where('account_status', $request->input('account_status'));
        }

        // #89: FILTER BY ROLE
        if ($request->filled('role')) {
            $query->where('role_id', $request->input('role'));
        }

        // #88: EAGER LOAD ROLE (prevent N+1 query)
        $users = $query->with(['role', 'group'])//this tells the query to also load the related role and group for each user, which prevents the N+1 query problem where a separate query would be executed for each user's role and group.
            ->paginate(15); // #88: Paginate 15 per page

        $roles = Role::all();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => $roles,
            'search' => $request->input('search'),
            'account_status' => $request->input('account_status'),
            'role' => $request->input('role'),
        ]);
    }

    // ============================================
    // SHOW CREATE USER FORM (System Admin only)
    // ============================================
    public function create()
    {
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can create new users');
        }

        $roles = Role::all();
        $groups = Group::whereNull('deleted_at')->orderBy('group_name')->get();

        return view('admin.users.create', [
            'roles' => $roles,
            'groups' => $groups,
        ]);
    }

    // ============================================
    // STORE NEW USER (System Admin only)
    // ============================================
    public function store(Request $request)
    {
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can create new users');
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'role_id' => 'required|exists:roles,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => $validated['role_id'],
            'group_id' => $validated['group_id'],
            'account_status' => 'active',
        ]);

        // Auto-promote first student in a student group to Group Admin
        Group::find($validated['group_id'])?->autoPromoteFirstStudent($user, auth()->id());

        // Audit log
        $this->auditLogService->logUserCreated($user);

        return redirect()->route('admin.users.show', $user)
            ->with('success', "User '{$user->full_name}' created successfully");
    }

    // ============================================
    // SHOW USER DETAIL PAGE
    // ============================================
    public function show($userId)
    {
        $currentUser = auth()->user();
        $user = User::with(['role', 'group'])->findOrFail($userId);

        // Authorization: System Admin can view any user, Group Admin only their scoped users
        if (! $currentUser->canAdminUser($user)) {
            abort(403, 'You do not have permission to view this user');
        }

        // Eager load related records for the detail page
        $warnings = $user->warnings()
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->get();

        $blacklistRecords = $user->blacklistRecords()
            ->with('liftedBy')
            ->orderBy('blacklisted_at', 'desc')
            ->get();

        $onboardingAgreements = $user->onboardingAgreements()
            ->orderBy('agreed_at', 'desc')
            ->get();

        return view('admin.users.show', [
            'user' => $user,
            'warnings' => $warnings,
            'blacklistRecords' => $blacklistRecords,
            'onboardingAgreements' => $onboardingAgreements,
        ]);
    }

    // ============================================
    // SHOW EDIT USER FORM
    // ============================================
    public function edit($userId)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($userId);

        // Authorization: System Admin can edit any user, Group Admin only their scoped users
        if (! $currentUser->canAdminUser($user)) {
            abort(403, 'You do not have permission to edit this user');
        }

        $roles = Role::all();
        $groups = Group::whereNull('deleted_at')->orderBy('group_name')->get();

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $roles,
            'groups' => $groups,
        ]);
    }

    // ============================================
    // UPDATE USER
    // ============================================
    public function update(Request $request, $userId)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($userId);

        // Authorization: System Admin can edit any user, Group Admin only their scoped users
        if (! $currentUser->canAdminUser($user)) {
            abort(403, 'You do not have permission to edit this user');
        }

        // Build validation rules based on admin role
        $rules = [
            'full_name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email,'.$user->id,
            'group_id' => 'required|exists:groups,id',
        ];

        // Only System Admin can change role and account_status
        if ($currentUser->isSystemAdmin()) {
            $rules['role_id'] = 'required|exists:roles,id';
            $rules['account_status'] = 'required|in:active,warned,blacklisted';
        }

        $validated = $request->validate($rules);

        // Capture old values for audit logging
        $oldRoleId = $user->role_id;
        $oldGroupId = $user->group_id;
        $oldStatus = $user->account_status;

        // Last admin protection: prevent downgrading the last System Administrator
        if ($currentUser->isSystemAdmin() && isset($validated['role_id'])) {
            $adminRole = Role::where('role_name', 'System Administrator')->first();
            if ($adminRole && $user->role_id === $adminRole->id && $validated['role_id'] != $adminRole->id) {
                $adminCount = User::where('role_id', $adminRole->id)->count();
                if ($adminCount === 1) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Cannot downgrade the last Administrator account');
                }
            }
        }

        // Build the update data
        $updateData = [
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'group_id' => $validated['group_id'],
        ];

        // Only System Admin can set role and status
        if ($currentUser->isSystemAdmin()) {
            $updateData['role_id'] = $validated['role_id'];
            $updateData['account_status'] = $validated['account_status'];
        }

        $user->update($updateData);

        // Auto-promote first student if user was moved to a new student group
        if ($oldGroupId != $user->group_id) {
            Group::find($user->group_id)?->autoPromoteFirstStudent($user, auth()->id());
        }

        // Audit log role change
        if ($currentUser->isSystemAdmin() && $oldRoleId != $user->role_id) {
            $this->auditLogService->logUserRoleChange($user, $oldRoleId, $user->role_id);
        }

        // Audit log group change
        if ($oldGroupId != $user->group_id) {
            $this->auditLogService->logUserGroupChange($user, $oldGroupId, $user->group_id);
        }

        // Audit log status change to active (reactivation)
        if ($currentUser->isSystemAdmin() && $oldStatus !== 'active' && $user->account_status === 'active') {
            $this->auditLogService->logUserActivated($user);
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', "User '{$user->full_name}' updated successfully");
    }

    // ============================================
    // #90: LIFT BLACKLIST ACTION
    // ============================================
    public function liftBlacklist($userId)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($userId);

        // Authorization: System Admin can manage any user, Group Admin only their scoped users
        if (! $currentUser->canAdminUser($user)) {
            abort(403, 'You do not have permission to manage this user');
        }

        // Find active blacklist record
        $blacklistRecord = BlacklistRecord::where('user_id', $userId)
            ->whereNull('lifted_at')
            ->first();

        if ($blacklistRecord) {
            // #90: Set lifted_at = now(), lifted_by = auth()->id()
            $blacklistRecord->update([
                'lifted_at' => now(),
                'lifted_by' => Auth::id(),
            ]);
        }

        // #90: Update user.account_status = active
        $user->update(['account_status' => 'active']);

        // Audit log
        $this->auditLogService->logBlacklistLifted($user);

        return redirect()->back()->with('success', "Blacklist lifted for {$user->full_name}");
    }

    // ============================================
    // #91: CHANGE USER ROLE
    // ============================================
    public function changeRole(Request $request, $userId)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($userId);

        // Authorization: System Admin can manage any user, Group Admin only their scoped users
        if (! $currentUser->canAdminUser($user)) {
            abort(403, 'You do not have permission to manage this user');
        }

        // Only System Admin can change roles
        if (! $currentUser->isSystemAdmin()) {
            abort(403, 'Only System Administrators can change user roles');
        }

        $newRoleId = $request->input('role_id');

        // #91: Prevent downgrading the last Administrator
        $adminRole = Role::where('role_name', 'System Administrator')->first();
        $adminCount = User::where('role_id', $adminRole->id)->count();

        if ($user->role_id === $adminRole->id && $adminCount === 1) {
            return redirect()->back()->with('error', 'Cannot downgrade the last Administrator account');
        }

        // #91: Update role
        $user->update(['role_id' => $newRoleId]);

        return redirect()->back()->with('success', "Role updated for {$user->full_name}");
    }

    // ============================================
    // DELETE USER (System Admin only)
    // ============================================
    public function destroy($userId)
    {
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can delete users');
        }

        $user = User::findOrFail($userId);

        // Cannot delete yourself
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account');
        }

        // Audit log before deletion (capture details first)
        $this->auditLogService->logUserDeleted($user);

        // Force delete the user — DB cascade handles warnings, tokens, etc.
        $user->forceDelete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully');
    }

    // ============================================
    // SHOW RESET PASSWORD FORM (System Admin only)
    // ============================================
    public function showResetPassword($userId)
    {
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can reset passwords');
        }

        $user = User::findOrFail($userId);

        return view('admin.users.reset-password', [
            'user' => $user,
        ]);
    }

    // ============================================
    // RESET USER PASSWORD (System Admin only)
    // ============================================
    public function resetPassword(Request $request, $userId)
    {
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can reset passwords');
        }

        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        // The 'hashed' cast on User model handles hashing automatically
        $user->update(['password' => $validated['password']]);

        // Audit log
        $this->auditLogService->logUserPasswordReset($user);

        return redirect()->route('admin.users.show', $user)
            ->with('success', "Password reset for '{$user->full_name}'");
    }

    // ============================================
    // SHOW BLACKLIST FORM (System Admin only)
    // ============================================
    public function showBlacklist($userId)
    {
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can blacklist users');
        }

        $user = User::findOrFail($userId);

        return view('admin.users.blacklist', [
            'user' => $user,
        ]);
    }

    // ============================================
    // BLACKLIST USER (System Admin only)
    // ============================================
    public function blacklist(Request $request, $userId)
    {
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can blacklist users');
        }

        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        // Calculate expires_at if duration provided, otherwise null (permanent)
        $expiresAt = null;
        $validated['duration_days'] = (int) $validated['duration_days'];
        if (! empty($validated['duration_days'])) {
            $expiresAt = now()->addDays($validated['duration_days']);
        }

        // Create blacklist record
        BlacklistRecord::create([
            'user_id' => $user->id,
            'reason' => $validated['reason'],
            'expires_at' => $expiresAt,
            'blacklisted_at' => now(),
        ]);

        // Update user status
        $user->update(['account_status' => 'blacklisted']);

        // Audit log
        $this->auditLogService->logUserBlacklisted($user, $validated['reason'], $expiresAt);

        return redirect()->route('admin.users.show', $user)
            ->with('success', "User '{$user->full_name}' has been blacklisted");
    }

    // ============================================
    // RESOLVE WARNING (System Admin only)
    // ============================================
    public function resolveWarning($warningId)
    {
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can resolve warnings');
        }

        $warning = Warning::with('user')->findOrFail($warningId);

        // Already resolved?
        if ($warning->is_resolved) {
            return redirect()->back()->with('error', 'This warning is already resolved');
        }

        // Mark as resolved
        $warning->update([
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);

        // Check if user has any remaining unresolved warnings
        $user = $warning->user;
        $unresolvedCount = $user->warnings()->where('is_resolved', false)->count();

        // If no unresolved warnings remain, change status from 'warned' to 'active'
        if ($unresolvedCount === 0 && $user->account_status === 'warned') {
            $user->update(['account_status' => 'active']);
        }

        // Audit log
        $this->auditLogService->logWarningResolved($warning);

        return redirect()->back()->with('success', "Warning resolved for '{$user->full_name}'");
    }
}
