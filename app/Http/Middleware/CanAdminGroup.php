<?php

namespace App\Http\Middleware;

use App\Models\Group;
use Closure;
use Illuminate\Http\Request;

class CanAdminGroup
{
    /**
     * Handle an incoming request.
     * Check if user can admin the specific group being accessed
     */
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            return redirect('/login')->with('error', 'Please login to continue');
        }

        $user = auth()->user();

        // Get group from route parameter
        $groupId = $request->route('group');

        if ($groupId instanceof Group) {
            $group = $groupId;
        } else {
            $group = Group::findOrFail($groupId);
        }

        // Check if user can admin this specific group
        if (! $user->canAdminGroup($group)) {
            abort(403, 'Unauthorized. You do not have permission to manage this group.');
        }

        return $next($request);
    }
}
