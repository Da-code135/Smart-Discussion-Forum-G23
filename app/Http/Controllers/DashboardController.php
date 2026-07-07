<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Services\RecommendationService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Show the main dashboard with recent topics and personalized recommendations.
     *
     * GET /dashboard
     */
    public function show()
    {
        $user = Auth::user();
        $recommendedTopics = collect();
        $recentTopics = collect();

        // System admins (group-agnostic) always see topics; others need a group
        if ($user->isSystemAdmin() || $user->group_id) {
            $topicQuery = Topic::where('status', 'active');

            // System admins see all topics; others see only accessible groups
            if (! $user->isSystemAdmin()) {
                $topicQuery->whereIn('group_id', $user->accessibleGroupIds());
            }

            $recentTopics = (clone $topicQuery)
                ->with('creator')
                ->withCount('posts')
                ->latest()
                ->take(5)
                ->get()
                ->map(
                    fn (Topic $topic) => [
                        'id' => $topic->id,
                        'title' => $topic->title,
                        'creator_name' => optional($topic->creator)->full_name ?? 'Deleted User',
                        'reply_count' => $topic->posts_count,
                        'created_at' => $topic->created_at,
                    ],
                );

            // --- Personalized recommendations via the RecommendationService ---
            $recommendations = app(RecommendationService::class)
                ->generateRecommendations($user, 3);

            $recommendedTopics = $recommendations->map(
                fn (Topic $topic) => [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'member_count' => $topic->posts_count,
                ],
            );
        }

        return view(
            'auth.dashboard',
            compact('recentTopics', 'recommendedTopics'),
        );
    }

    /**
     * Show the full recommendations page.
     *
     * GET /recommendations
     */
    public function showRecommendations()
    {
        $user = Auth::user();
        $recommendations = app(RecommendationService::class)
            ->generateRecommendations($user, 10);

        return view('recommendations.index', compact('recommendations'));
    }
}
