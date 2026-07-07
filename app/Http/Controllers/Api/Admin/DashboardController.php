<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * GET /api/v1/admin/dashboard
     *
     * Returns aggregated platform statistics for the admin dashboard.
     *
     * Group Administrators see stats scoped to their assigned groups.
     * System Administrators see full platform-wide stats.
     */
    public function index()
    {
        $user = Auth::user();

        if ($user->isGroupAdmin()) {
            // Group Admin: scope queries to assigned groups only
            $groupIds = $user->administeredGroups()->pluck('groups.id');

            $totalUsers = User::whereIn('group_id', $groupIds)->count();
            $activeUsers = User::whereIn('group_id', $groupIds)
                ->where('last_active_at', '>=', now()->subDays(30))
                ->count();

            $totalTopics = Topic::whereIn('group_id', $groupIds)->count();

            // Posts don't have group_id directly — count via topics
            $topicIds = Topic::whereIn('group_id', $groupIds)->pluck('id');
            $totalPosts = Post::whereIn('topic_id', $topicIds)->count();
            $reportedPosts = Post::whereIn('topic_id', $topicIds)
                ->reported()
                ->count();

            $totalGroups = count($groupIds);
        } else {
            // System Admin: full platform stats
            $totalUsers = User::count();
            $activeUsers = User::where('last_active_at', '>=', now()->subDays(30))
                ->count();

            $totalTopics = Topic::count();
            $totalPosts = Post::count();
            $totalGroups = Group::count();
            $reportedPosts = Post::reported()->count();
        }

        // Platform-wide stats (not group-scoped)
        $quizzesToday = Quiz::whereDate('scheduled_date', today())->count();
        $recentRegistrations = User::where('created_at', '>=', now()->subDays(7))
            ->count();

        // Recent topics (last 7)
        $recentTopics = Topic::with('creator:id,full_name')
            ->latest()
            ->take(7)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'creator' => $t->creator->full_name ?? 'Deleted',
                'created_at' => $t->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'total_groups' => $totalGroups,
                    'total_topics' => $totalTopics,
                    'total_posts' => $totalPosts,
                    'reported_posts' => $reportedPosts,
                    'quizzes_today' => $quizzesToday,
                    'recent_registrations_7d' => $recentRegistrations,
                ],
                'recent_topics' => $recentTopics,
            ],
        ]);
    }
}
