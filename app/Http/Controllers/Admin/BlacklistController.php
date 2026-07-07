<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlacklistRecord;
use App\Models\User;
use App\Services\WarningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BlacklistController extends Controller
{
    protected WarningService $warningService;

    public function __construct(WarningService $warningService)
    {
        $this->warningService = $warningService;
    }

    /**
     * Display a listing of blacklist records (group-scoped for Group Admins)
     */
    public function index(Request $request)
    {
        $currentUser = auth()->user();

        // Base query
        $query = BlacklistRecord::with(['user', 'liftedBy']);

        // Apply group scoping for Group Admins
        if ($currentUser->isGroupAdmin()) {
            $adminGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
            $query->whereIn('user_id', User::whereIn('group_id', $adminGroupIds)->pluck('id'));
        }

        // Filter by search term
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('full_name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        // Get paginated results
        $blacklistRecords = $query->latest()->paginate(15);

        return view('admin.blacklist.index', [
            'blacklistRecords' => $blacklistRecords,
            'search' => $request->input('search'),
        ]);
    }

    /**
     * Create a new blacklist record
     */
    public function store(Request $request)
    {
        // Authorization check - only admins can blacklist users
        if (! Gate::allows('create', BlacklistRecord::class)) {
            abort(403, 'Only administrators can blacklist users');
        }

        // Validate request
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'required|string|max:1000',
        ]);

        // Get the target user and current admin
        $targetUser = User::findOrFail($validated['user_id']);
        $adminUser = auth()->user();

        // Verify admin can manage this user
        if (! $adminUser->canAdminUser($targetUser)) {
            abort(403, 'You do not have permission to blacklist this user');
        }

        // Create blacklist record
        $blacklistRecord = BlacklistRecord::create([
            'user_id' => $targetUser->id,
            'reason' => $validated['reason'],
            'blacklisted_at' => now(),
        ]);

        // Update user's account status
        $targetUser->update([
            'account_status' => 'blacklisted',
            'blacklisted_at' => now(),
        ]);

        return redirect()->route('admin.blacklist.show', $blacklistRecord)
            ->with('success', 'User blacklisted successfully');
    }

    /**
     * Display a specific blacklist record
     */
    public function show(BlacklistRecord $blacklistRecord)
    {
        // Authorization check
        if (! Gate::allows('view-blacklist', $blacklistRecord)) {
            abort(403, 'You do not have permission to view this blacklist record');
        }

        return view('admin.blacklist.show', [
            'blacklistRecord' => $blacklistRecord,
        ]);
    }

    /**
     * Lift a blacklist record
     */
    public function update(Request $request, BlacklistRecord $blacklistRecord)
    {
        // Authorization check
        if (! Gate::allows('update', $blacklistRecord)) {
            abort(403, 'You do not have permission to update this blacklist record');
        }

        // Get the admin user
        $adminUser = auth()->user();

        // Lift the blacklist using the service
        $success = $this->warningService->liftBlacklist($blacklistRecord, $adminUser);

        if ($success) {
            return redirect()->route('admin.blacklist.show', $blacklistRecord)
                ->with('success', 'Blacklist lifted successfully');
        }

        return redirect()->route('admin.blacklist.show', $blacklistRecord)
            ->with('error', 'Failed to lift blacklist');
    }
}
