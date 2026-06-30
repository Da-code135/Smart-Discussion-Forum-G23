<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdvancedSearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    protected AdvancedSearchService $searchService;

    public function __construct(AdvancedSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * POST /api/v1/admin/search/users
     * Advanced user search
     */
    public function searchUsers(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        // Get allowed group IDs for Group Admins
        $allowedGroupIds = null;
        if ($currentUser->isGroupAdmin()) {
            $allowedGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
        }

        $users = $this->searchService->searchUsers(
            $request,
            $currentUser->isSystemAdmin(),
            $allowedGroupIds
        );

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'filters' => $request->only([
                'search',
                'account_status',
                'role_id',
                'group_id',
                'registered_from',
                'registered_to',
                'last_active_from',
                'last_active_to',
                'email_verified',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    /**
     * POST /api/v1/admin/search/groups
     * Advanced group search
     */
    public function searchGroups(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        // Get allowed group IDs for Group Admins
        $allowedGroupIds = null;
        if ($currentUser->isGroupAdmin()) {
            $allowedGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
        }

        $groups = $this->searchService->searchGroups(
            $request,
            $currentUser->isSystemAdmin(),
            $allowedGroupIds
        );

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
            'filters' => $request->only([
                'search',
                'created_from',
                'created_to',
                'min_members',
                'max_members',
                'created_by',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    /**
     * POST /api/v1/admin/search/audit-logs
     * Advanced audit log search
     */
    public function searchAuditLogs(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $logs = $this->searchService->searchAuditLogs($request);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
            'filters' => $request->only([
                'search',
                'action',
                'user_id',
                'target_type',
                'ip_address',
                'start_date',
                'end_date',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    /**
     * POST /api/v1/admin/search/warnings
     * Advanced warning search
     */
    public function searchWarnings(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        // Get allowed group IDs for Group Admins
        $allowedGroupIds = null;
        if ($currentUser->isGroupAdmin()) {
            $allowedGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
        }

        $warnings = $this->searchService->searchWarnings(
            $request,
            $currentUser->isSystemAdmin(),
            $allowedGroupIds
        );

        return response()->json([
            'success' => true,
            'data' => $warnings->items(),
            'pagination' => [
                'total' => $warnings->total(),
                'per_page' => $warnings->perPage(),
                'current_page' => $warnings->currentPage(),
                'last_page' => $warnings->lastPage(),
                'from' => $warnings->firstItem(),
                'to' => $warnings->lastItem(),
            ],
            'filters' => $request->only([
                'search',
                'user_id',
                'is_acknowledged',
                'is_resolved',
                'warning_number',
                'issued_from',
                'issued_to',
                'deadline_from',
                'deadline_to',
                'overdue',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    /**
     * GET /api/v1/admin/search/options/{model}
     * Get available sort and filter options for a model
     */
    public function getOptions($model)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $allowedModels = ['users', 'groups', 'audit_logs', 'warnings'];
        
        if (!in_array($model, $allowedModels)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid model. Allowed: ' . implode(', ', $allowedModels)
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sort_options' => $this->searchService->getSortOptions($model),
                'filter_options' => $this->searchService->getFilterOptions($model),
            ]
        ]);
    }

    /**
     * GET /api/v1/admin/search/suggestions/{type}
     * Get search suggestions (autocomplete)
     */
    public function getSuggestions(Request $request, $type)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $query = $request->input('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $suggestions = [];

        switch ($type) {
            case 'users':
                $suggestions = \App\Models\User::where('full_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->limit(10)
                    ->get(['id', 'full_name', 'email'])
                    ->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'label' => "{$user->full_name} ({$user->email})",
                            'value' => $user->full_name,
                        ];
                    });
                break;

            case 'groups':
                $suggestions = \App\Models\Group::where('group_name', 'like', "%{$query}%")
                    ->limit(10)
                    ->get(['id', 'group_name'])
                    ->map(function ($group) {
                        return [
                            'id' => $group->id,
                            'label' => $group->group_name,
                            'value' => $group->group_name,
                        ];
                    });
                break;
        }

        return response()->json([
            'success' => true,
            'data' => $suggestions
        ]);
    }
}
