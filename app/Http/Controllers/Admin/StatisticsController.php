<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Statistics;
use App\Utilities\StatisticsUtility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controller for the Statistics Dashboard (Tasks 1 & 2 of the Analytics module).
 *
 * Displays engagement metrics per group and provides a "Recalculate" action
 * to recompute the snapshot from live data.
 */
class StatisticsController extends Controller
{
    public function __construct(
        protected StatisticsUtility $statisticsUtility
    ) {}

    /**
     * Show the statistics dashboard.
     *
     * - System Administrators see stats for ALL groups.
     * - Group Administrators see stats only for the groups they administer.
     *
     * If a group doesn't have a statistics row yet, one is created
     * on-the-fly with default zero values so the dashboard never appears empty.
     */
    public function index(): View
    {
        $groupStats = $this->statisticsUtility->getStatsForUser(Auth::user());

        return view('admin.statistics.index', compact('groupStats'));
    }

    /**
     * Recalculate statistics for a given group from live data.
     *
     * Queries the actual users, topics, and posts tables to recompute
     * every metric, then updates the statistics row with fresh values
     * and a new last_calculated_at timestamp.
     *
     * Access control: the caller must already be behind the 'admin'
     * middleware, but we also verify they can admin this specific group.
     */
    public function recalculate(int $groupId): RedirectResponse
    {
        $user = Auth::user();
        $group = Group::findOrFail($groupId);

        // Authorise: the user must be able to access this group
        if (! $user->canAccessGroup($groupId)) {
            abort(403, 'You do not have access to statistics for this group.');
        }

        $this->statisticsUtility->recalculate($groupId);

        return redirect()
            ->route('admin.statistics.index')
            ->with('success', "Statistics recalculated for {$group->group_name}.");
    }
}
