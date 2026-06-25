<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\BlacklistRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserManagementController extends Controller
{
    // ============================================
    // #88: SHOW USERS TABLE
    // ============================================
    public function index(Request $request)
    {
        $query = User::query();

        // #89: SEARCH FUNCTIONALITY
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
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
        $users = $query->with('role')
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
    // #90: LIFT BLACKLIST ACTION
    // ============================================
    public function liftBlacklist($userId)
    {
        $user = User::findOrFail($userId);

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

        return redirect()->back()->with('success', "Blacklist lifted for {$user->full_name}");
    }

    // ============================================
    // #91: CHANGE USER ROLE
    // ============================================
    public function changeRole(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $newRoleId = $request->input('role_id');

        // #91: Prevent downgrading the last Administrator
        $adminRole = Role::where('role_name', 'Administrator')->first();
        $adminCount = User::where('role_id', $adminRole->id)->count();

        if ($user->role_id === $adminRole->id && $adminCount === 1) {
            return redirect()->back()->with('error', 'Cannot downgrade the last Administrator account');
        }

        // #91: Update role
        $user->update(['role_id' => $newRoleId]);

        return redirect()->back()->with('success', "Role updated for {$user->full_name}");
    }
}
