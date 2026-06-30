<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class GroupBrowseController extends Controller
{
    /**
     * G1: List groups the authenticated user belongs to.
     *
     * GET /api/v1/groups
     *
     * Regular users see only their own group.
     * Admins see groups they administer.
     * System admins see all groups.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isSystemAdmin()) {
            // System admins can browse all groups
            $groups = Group::withCount('users')
                ->orderBy('group_name')
                ->paginate(20);
        } elseif ($user->isGroupAdmin()) {
            // Group admins see groups they administer plus their own
            $adminGroupIds = $user->administeredGroups()->pluck('groups.id');

            $groups = Group::whereIn('id', $adminGroupIds)
                ->orWhere('id', $user->group_id)
                ->withCount('users')
                ->orderBy('group_name')
                ->paginate(20);
        } else {
            // Regular users see only their own group
            $groups = Group::where('id', $user->group_id)
                ->withCount('users')
                ->get();
        }

        return response()->json([
            'data' => $groups,
        ], 200);
    }

    /**
     * G2: Show a single group's details.
     *
     * GET /api/v1/groups/{groupId}
     *
     * Group isolation: users can only view groups they belong to (unless admin).
     */
    public function show(Request $request, int $groupId)
    {
        $user = $request->user();

        $group = Group::findOrFail($groupId);

        $this->enforceGroupAccess($user, $group);

        $group->loadCount('users');

        $group->load(['createdBy' => function ($q) {
            $q->select('id', 'full_name');
        }]);

        return response()->json([
            'data' => [
                'id' => $group->id,
                'group_name' => $group->group_name,
                'description' => $group->description,
                'members_count' => $group->users_count,
                'created_by' => $group->createdBy ? [
                    'id' => $group->createdBy->id,
                    'full_name' => $group->createdBy->full_name,
                ] : null,
                'created_at' => $group->created_at,
                'updated_at' => $group->updated_at,
            ],
        ], 200);
    }

    /**
     * G3: List topics in a specific group.
     *
     * GET /api/v1/groups/{groupId}/topics
     *
     * Group isolation enforced via group membership check.
     * Returns paginated active topics with creator info and post count.
     */
    public function topics(Request $request, int $groupId)
    {
        $user = $request->user();

        $group = Group::findOrFail($groupId);

        $this->enforceGroupAccess($user, $group);

        $topics = Topic::forGroup($group->id)
            ->active()
            ->with('creator')
            ->withCount('posts')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $topics,
        ], 200);
    }

    /**
     * G4: List members of a specific group.
     *
     * GET /api/v1/groups/{groupId}/members
     *
     * Group isolation enforced via group membership check.
     * Returns paginated list of users in the group.
     */
    public function members(Request $request, int $groupId)
    {
        $user = $request->user();

        $group = Group::findOrFail($groupId);

        $this->enforceGroupAccess($user, $group);

        $members = User::where('group_id', $group->id)
            ->select('id', 'full_name', 'email', 'role_id', 'account_status', 'last_active_at', 'profile_picture', 'created_at')
            ->with('role:id,role_name')
            ->orderBy('full_name')
            ->paginate(20);

        return response()->json([
            'data' => $members,
        ], 200);
    }

    // ------------------------------------------------------------------
    // Shared group isolation enforcement
    // ------------------------------------------------------------------

    /**
     * Enforce group isolation:
     * - System admins pass through (they see everything).
     * - Group admins must belong to or administer the group.
     * - Regular users can only access their own group.
     *
     * @throws ModelNotFoundException (via abort 403)
     */
    private function enforceGroupAccess(User $user, Group $group): void
    {
        // System admins can access any group
        if ($user->isSystemAdmin()) {
            return;
        }

        // User is a member of this group
        if ($group->id === $user->group_id) {
            return;
        }

        // Group admin who administers this group
        if ($user->isGroupAdmin()) {
            $isAdminOf = $user->administeredGroups()
                ->where('groups.id', $group->id)
                ->exists();

            if ($isAdminOf) {
                return;
            }
        }

        abort(403, 'You do not have access to this group.');
    }
}
