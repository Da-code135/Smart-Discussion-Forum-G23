<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Warning;
use App\Services\WarningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WarningController extends Controller
{
    protected WarningService $warningService;

    public function __construct(WarningService $warningService)
    {
        $this->warningService = $warningService;
    }

    /**
     * Display a listing of warnings (group-scoped for Group Admins)
     */
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        
        // Base query
        $query = Warning::with(['user', 'createdBy']);

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
        $warnings = $query->latest()->paginate(15);

        return view('admin.warnings.index', [
            'warnings' => $warnings,
            'search' => $request->input('search'),
        ]);
    }

    /**
     * Display a specific warning
     */
    public function show(Warning $warning)
    {
        // Authorization check
        if (!Gate::allows('view-warning', $warning)) {
            abort(403, 'You do not have permission to view this warning');
        }

        return view('admin.warnings.show', [
            'warning' => $warning,
        ]);
    }

    /**
     * Create a new warning
     */
    public function store(Request $request)
    {
        // Authorization check - only admins can create warnings
        if (!Gate::allows('create', Warning::class)) {
            abort(403, 'Only administrators can issue warnings');
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
        if (!$adminUser->canAdminUser($targetUser)) {
            abort(403, 'You do not have permission to warn this user');
        }

        // Issue the warning using the service
        $warning = $this->warningService->issueWarning(
            $targetUser,
            $adminUser,
            $validated['reason']
        );

        return redirect()->route('admin.warnings.show', $warning)
                       ->with('success', 'Warning issued successfully');
    }

    /**
     * Resolve a warning
     */
    public function update(Request $request, Warning $warning)
    {
        // Authorization check
        if (!Gate::allows('update', $warning)) {
            abort(403, 'You do not have permission to update this warning');
        }

        // Get the admin user
        $adminUser = auth()->user();

        // Resolve the warning using the service
        $success = $this->warningService->resolveWarning($warning, $adminUser);

        if ($success) {
            return redirect()->route('admin.warnings.show', $warning)
                           ->with('success', 'Warning resolved successfully');
        }

        return redirect()->route('admin.warnings.show', $warning)
                       ->with('error', 'Failed to resolve warning');
    }
}