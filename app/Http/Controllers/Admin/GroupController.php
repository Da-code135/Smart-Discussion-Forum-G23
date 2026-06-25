<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    // ============================================
    // #143: SHOW GROUPS TABLE
    // ============================================
    public function index(Request $request)
    {
        $query = Group::withCount('users'); // Count members in each group

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
    // #145: SHOW CREATE FORM
    // ============================================
    public function create()
    {
        return view('admin.groups.create');
    }

    // ============================================
    // #145: STORE NEW GROUP
    // ============================================
    public function store(Request $request)
    {
        // #145: Validate fields
        $validated = $request->validate([
            'group_name' => 'required|string|max:100|unique:groups',
            'description' => 'nullable|string|max:500',
        ]);

        // #145: Store with created_by = auth()->id()
        Group::create([
            'group_name' => $validated['group_name'],
            'description' => $validated['description'],
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.groups.index')
                       ->with('success', 'Group created successfully');
    }

    // ============================================
    // #146: SHOW EDIT FORM
    // ============================================
    public function edit(Group $group)
    {
        return view('admin.groups.edit', [
            'group' => $group,
        ]);
    }

    // ============================================
    // #146: UPDATE GROUP
    // ============================================
    public function update(Request $request, Group $group)
    {
        // #146: Same validation as create
        $validated = $request->validate([
            'group_name' => 'required|string|max:100|unique:groups,group_name,' . $group->id,
            'description' => 'nullable|string|max:500',
        ]);

        // #146: Update group
        $group->update($validated);

        return redirect()->route('admin.groups.index')
                       ->with('success', 'Group updated successfully');
    }

    // ============================================
    // #146: DELETE GROUP (with confirmation)
    // ============================================
    public function destroy(Group $group)
    {
        // Prevent deleting 'General' group if it's the default
        if ($group->group_name === 'General') {
            return redirect()->back()->with('error', 'Cannot delete the General group');
        }

        $group->delete(); // Soft delete or hard delete based on your config

        return redirect()->route('admin.groups.index')
                       ->with('success', 'Group deleted successfully');
    }

    // ============================================
    // #147: SHOW GROUP MEMBERSHIP MANAGER
    // ============================================
    public function showMembers(Group $group)
    {
        // All users in this group
        $memberIds = $group->users()->pluck('users.id')->toArray();

        // All users for assignment
        $allUsers = User::all();

        return view('admin.groups.members', [
            'group' => $group,
            'memberIds' => $memberIds,
            'allUsers' => $allUsers,
        ]);
    }

    // ============================================
    // #147: UPDATE GROUP MEMBERSHIP
    // ============================================
    public function updateMembers(Request $request, Group $group)
    {
        $selectedUserIds = $request->input('user_ids', []);

        // #147: Prevent removing last member from 'General' group
        if ($group->group_name === 'General' && count($selectedUserIds) === 0) {
            return redirect()->back()->with('error', 'Cannot remove all members from General group');
        }

        // Sync users (remove those not in selectedUserIds, add those in it)
        $group->users()->sync($selectedUserIds);

        return redirect()->back()->with('success', 'Group members updated successfully');
    }

    // ============================================
    // #148: BULK ASSIGN USERS TO GROUP (optional)
    // ============================================
    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'group_id' => 'required|exists:groups,id',
        ]);

        // Update all selected users' group_id
        User::whereIn('id', $validated['user_ids'])
            ->update(['group_id' => $validated['group_id']]);

        return redirect()->back()->with('success', 'Users assigned to group successfully');
    }
}
