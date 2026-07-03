<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\GroupStatisticsService;

class GroupStatisticsController extends Controller
{
    public function __construct(
        protected GroupStatisticsService $statsService
    ) {}

    /**
     * List all groups with summary stats.
     * Only System Administrators can access (enforced by route middleware).
     */
    public function index()
    {
        $groups = $this->statsService->allGroupsOverview();

        return view('admin.group-statistics.index', compact('groups'));
    }

    /**
     * Detailed stats for a single group.
     */
    public function show(Group $group)
    {
        $stats = $this->statsService->groupDetail($group);

        return view('admin.group-statistics.show', $stats);
    }
}
