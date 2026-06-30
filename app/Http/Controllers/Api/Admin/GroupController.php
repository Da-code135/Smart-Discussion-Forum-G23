<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class GroupController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * GET /api/v1/admin/groups
     * List all groups (with role-based filtering)
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

        $query = Group::withCount('users');

        // Role-based filtering: Group Admins see only their assigned groups
        if ($currentUser->isGroupAdmin()) {
            $adminGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
            $query->whereIn('id', $adminGroupIds);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('group_name', 'like', "%{$search}%");
        }

        // Sort functionality
        $sortBy = $request->input('sort_by', 'created_at');
        if ($sortBy === 'member_count') {
            $query->orderBy('users_count', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $groups = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $groups->items(),
            'pagination' => [
                'total' => $groups->total(),
                'per_page' => $groups->perPage(),
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'from' => $groups->firstItem(),
                'to' => $groups->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/groups/{groupId}
     * Get specific group details
     */
    public function show($groupId)
    {
        $group = Group::withCount('users')->findOrFail($groupId);

        // Authorization check
        if (!Gate::allows('view', $group)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You cannot view this group.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $group,
        ]);
    }

    /**
     * POST /api/v1/admin/groups
     * Create new group (System Admin only)
     */
    public function store(Request $request)
    {
        // Authorization check - System Admin only
        if (!auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can create new groups'
            ], 403);
        }

        $validated = $request->validate([
            'group_name' => 'required|string|max:100|unique:groups',
            'description' => 'nullable|string|max:500',
        ]);

        $group = Group::create([
            'group_name' => $validated['group_name'],
            'description' => $validated['description'],
            'created_by' => Auth::id(),
        ]);

        // Audit log
        $this->auditLogService->logGroupCreated($group);

        return response()->json([
            'success' => true,
            'message' => 'Group created successfully',
            'data' => $group,
        ], 201);
    }

    /**
     * PUT /api/v1/admin/groups/{groupId}
     * Update group
     */
    public function update(Request $request, $groupId)
    {
        $group = Group::findOrFail($groupId);

        // Authorization check
        if (!Gate::allows('update', $group)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this group'
            ], 403);
        }

        $validated = $request->validate([
            'group_name' => 'required|string|max:100|unique:groups,group_name,' . $group->id,
            'description' => 'nullable|string|max:500',
        ]);

        $oldValues = $group->only(['group_name', 'description']);

        $group->update($validated);

        // Audit log
        $this->auditLogService->logGroupUpdated($group, $oldValues);

        return response()->json([
            'success' => true,
            'message' => 'Group updated successfully',
            'data' => $group,
        ]);
    }

    /**
     * DELETE /api/v1/admin/groups/{groupId}
     * Delete group (System Admin only)
     */
    public function destroy($groupId)
    {
        $group = Group::findOrFail($groupId);

        // Authorization check - System Admin only
        if (!Gate::allows('delete', $group)) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can delete groups'
            ], 403);
        }

        // Prevent deleting 'General' group
        if ($group->group_name === 'General') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the General group'
            ], 400);
        }

        // Audit log
        $this->auditLogService->logGroupDeleted($group);

        $group->delete();

        return response()->json([
            'success' => true,
            'message' => 'Group deleted successfully',
        ]);
    }

    /**
     * GET /api/v1/admin/groups/{groupId}/members
     * Get group members
     */
    public function showMembers($groupId)
    {
        $group = Group::findOrFail($groupId);

        // Authorization check
        if (!Gate::allows('manageMembers', $group)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view members of this group'
            ], 403);
        }

        $members = $group->users()->with(['role'])->get();

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    /**
     * PUT /api/v1/admin/groups/{groupId}/members
     * Update group membership
     */
    public function updateMembers(Request $request, $groupId)
    {
        $group = Group::findOrFail($groupId);

        // Authorization check
        if (!Gate::allows('manageMembers', $group)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to manage members of this group'
            ], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        // Prevent removing last member from 'General' group
        if ($group->group_name === 'General' && count($validated['user_ids']) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove all members from General group'
            ], 400);
        }

        $group->users()->sync($validated['user_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Group members updated successfully',
        ]);
    }

    /**
     * POST /api/v1/admin/groups/{groupId}/admins
     * Add admin to group (System Admin only)
     */
    public function addAdmin(Request $request, $groupId)
    {
        // Authorization check - System Admin only
        if (!auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can assign group admins'
            ], 403);
        }

        $group = Group::findOrFail($groupId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($validated['user_id']);

        // Check if user is Group Administrator
        if (!$user->isGroupAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'User must have Group Administrator role'
            ], 400);
        }

        $group->addAdmin($user, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Admin added to group successfully',
        ]);
    }

    /**
     * DELETE /api/v1/admin/groups/{groupId}/admins/{userId}
     * Remove admin from group (System Admin only)
     */
    public function removeAdmin($groupId, $userId)
    {
        // Authorization check - System Admin only
        if (!auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can remove group admins'
            ], 403);
        }

        $group = Group::findOrFail($groupId);
        $user = User::findOrFail($userId);

        $group->removeAdmin($user);

        return response()->json([
            'success' => true,
            'message' => 'Admin removed from group successfully',
        ]);
    }
}
