<?php

namespace App\Http\Controllers\Admin;

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

    // ============================================
    // #143: SHOW GROUPS TABLE (with role-based filtering)
    // ============================================
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $query = Group::withCount('users')->with('createdBy');

        // Role-based filtering: Group Admins see only their assigned groups
        if ($currentUser->isGroupAdmin()) {
            $adminGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
            $query->whereIn('id', $adminGroupIds);
        }
        // System Admins see all groups (no filter needed)

        // #144: SEARCH BY GROUP NAME
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('group_name', 'like', "%{$search}%");
        }

        // #144: SORT BY CREATION DATE OR MEMBER COUNT
        $sortBy = $request->input('sort_by', 'created_at');
        if ($sortBy === 'member_count') {
            $query->orderBy('users_count', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $groups = $query->paginate(15); // #143: Paginate

        return view('admin.groups.index', [
            'groups' => $groups,
            'search' => $request->input('search'),
            'sort_by' => $sortBy,
        ]);
    }

    // ============================================
    // #145: SHOW CREATE FORM (System Admin only)
    // ============================================
    public function create()
    {
        // Authorization check - only System Admins can create groups
        if (! Gate::allows('create', Group::class)) {
            abort(403, 'Only System Administrators can create new groups');
        }

        return view('admin.groups.create', [
            'group' => new Group,
        ]);
    }

    // ============================================
    // #145: STORE NEW GROUP (System Admin only)
    // ============================================
    public function store(Request $request)
    {
        // Authorization check - only System Admins can create groups
        if (! Gate::allows('create', Group::class)) {
            abort(403, 'Only System Administrators can create new groups');
        }

        // #145: Validate fields
        $validated = $request->validate([
            'group_name' => 'required|string|max:100|unique:groups',
            'description' => 'nullable|string|max:500',
            'group_type' => 'required|in:sysadmin,lecturer,student',
        ]);

        // #145: Store with created_by = auth()->id()
        $group = Group::create([
            'group_name' => $validated['group_name'],
            'description' => $validated['description'],
            'group_type' => $validated['group_type'],
            'created_by' => Auth::id(),
        ]);

        // Audit log
        $this->auditLogService->logGroupCreated($group);

        return redirect()->route('admin.groups.index')
            ->with('success', 'Group created successfully');
    }

    // ============================================
    // #146: SHOW EDIT FORM (with authorization)
    // ============================================
    public function edit(Group $group)
    {
        // Authorization check
        if (! Gate::allows('update', $group)) {
            abort(403, 'You do not have permission to edit this group');
        }

        return view('admin.groups.edit', [
            'group' => $group,
        ]);
    }

    // ============================================
    // #146: UPDATE GROUP (with authorization)
    // ============================================
    public function update(Request $request, Group $group)
    {
        // Authorization check
        if (! Gate::allows('update', $group)) {
            abort(403, 'You do not have permission to update this group');
        }

        // #146: Same validation as create
        $validated = $request->validate([
            'group_name' => 'required|string|max:100|unique:groups,group_name,'.$group->id,
            'description' => 'nullable|string|max:500',
            'group_type' => 'required|in:sysadmin,lecturer,student',
        ]);

        $oldValues = $group->only(['group_name', 'description', 'group_type']);

        // #146: Update group
        $group->update($validated);

        // Audit log
        $this->auditLogService->logGroupUpdated($group, $oldValues);

        return redirect()->route('admin.groups.index')
            ->with('success', 'Group updated successfully');
    }

    // ============================================
    // #146: DELETE GROUP (System Admin only)
    // ============================================
    public function destroy(Group $group)
    {
        // Authorization check - only System Admins can delete groups
        if (! Gate::allows('delete', $group)) {
            abort(403, 'Only System Administrators can delete groups');
        }

        // Prevent deleting 'General' group if it's the default
        if ($group->group_name === 'General') {
            return redirect()->back()->with('error', 'Cannot delete the General group');
        }

        // Reassign users in this group to the default group before soft-deleting
        $defaultGroupId = Group::where('group_name', 'General')->value('id')
            ?? Group::min('id');

        if ($defaultGroupId && $defaultGroupId != $group->id) {
            User::where('group_id', $group->id)
                ->update(['group_id' => $defaultGroupId]);
        }

        // Audit log
        $this->auditLogService->logGroupDeleted($group);

        $group->delete(); // Soft delete or hard delete based on your config

        return redirect()->route('admin.groups.index')
            ->with('success', 'Group deleted successfully');
    }

    // ============================================
    // #147: SHOW GROUP MEMBERSHIP MANAGER (with authorization)
    // ============================================
    public function showMembers(Group $group)
    {
        // Authorization check
        if (! Gate::allows('manageMembers', $group)) {
            abort(403, 'You do not have permission to manage members of this group');
        }

        // All users in this group
        $memberIds = $group->users()->pluck('users.id')->toArray();

        // All users for assignment (filtered by admin type)
        $currentUser = auth()->user();
        if ($currentUser->isGroupAdmin()) {
            // Group admins see only users in their groups
            $adminGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
            $allUsers = User::whereIn('group_id', $adminGroupIds)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'email', 'group_id']);
        } else {
            // System admins see all users (select only needed columns to reduce memory)
            $allUsers = User::orderBy('full_name')
                ->get(['id', 'full_name', 'email', 'group_id']);
        }

        return view('admin.groups.members', [
            'group' => $group,
            'memberIds' => $memberIds,
            'allUsers' => $allUsers,
        ]);
    }

    // ============================================
    // #147: UPDATE GROUP MEMBERSHIP (with authorization)
    // ============================================
    public function updateMembers(Request $request, Group $group)
    {
        // Authorization check
        if (! Gate::allows('manageMembers', $group)) {
            abort(403, 'You do not have permission to manage members of this group');
        }

        $selectedUserIds = $request->input('user_ids', []);

        // #147: Prevent removing last member from 'General' group
        if ($group->group_name === 'General' && count($selectedUserIds) === 0) {
            return redirect()->back()->with('error', 'Cannot remove all members from General group');
        }

        // Remove users currently in this group who are not in the selected list
        // Move them to the default group (group_id cannot be null)
        $defaultGroupId = Group::where('group_name', 'General')->value('id')
            ?? Group::min('id');

        if ($defaultGroupId != $group->id) {
            User::where('group_id', $group->id)
                ->whereNotIn('id', $selectedUserIds)
                ->update(['group_id' => $defaultGroupId]);
        }

        // Add selected users to this group
        $newMembers = User::whereIn('id', $selectedUserIds)
            ->where('group_id', '!=', $group->id)
            ->get();

        foreach ($newMembers as $member) {
            $member->update(['group_id' => $group->id]);
            // Auto-promote first student in a student group to Group Admin
            $group->autoPromoteFirstStudent($member, auth()->id());
        }

        return redirect()->back()->with('success', 'Group members updated successfully');
    }

    // ============================================
    // #148: BULK ASSIGN USERS TO GROUP (System Admin only)
    // ============================================
    public function bulkAssign(Request $request)
    {
        // Authorization check - only System Admins can bulk assign
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can bulk assign users');
        }

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'group_id' => 'required|exists:groups,id',
        ]);

        $group = Group::findOrFail($validated['group_id']);

        // Fetch users, assign them, and check auto-promotion
        $users = User::whereIn('id', $validated['user_ids'])->get();

        foreach ($users as $user) {
            $user->update(['group_id' => $group->id]);
            $group->autoPromoteFirstStudent($user, auth()->id());
        }

        return redirect()->back()->with('success', 'Users assigned to group successfully');
    }

    // ============================================
    // SHOW TRASHED (SOFT-DELETED) GROUPS
    // ============================================
    public function trashed(Request $request)
    {
        $query = Group::onlyTrashed()->withCount('users')->with('createdBy');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('group_name', 'like', "%{$search}%");
        }

        $query->orderBy('deleted_at', 'desc');
        $groups = $query->paginate(15);

        return view('admin.groups.trashed', [
            'groups' => $groups,
            'search' => $request->input('search'),
        ]);
    }

    // ============================================
    // RESTORE SOFT-DELETED GROUP (System Admin only)
    // ============================================
    public function restore(Group $group)
    {
        // Authorization check
        if (! Gate::allows('restore', $group)) {
            abort(403, 'Only System Administrators can restore groups');
        }

        // Restore the group (sets deleted_at = null)
        $group->restore();

        // Audit log
        $this->auditLogService->logGroupRestored($group);

        return redirect()->route('admin.groups.trashed')
            ->with('success', "Group '{$group->group_name}' restored successfully");
    }
}
