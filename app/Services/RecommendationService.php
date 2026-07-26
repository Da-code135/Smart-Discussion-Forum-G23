<?php

namespace App\Services;

use App\Models\RecommendationLog;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class RecommendationService
{
    /**
     * Generate personalized topic recommendations for a user.
     *
     * Strategy:
     * 1. Find which topic categories the user has engaged with (posted in).
     * 2. Find active topics in those categories they haven't seen or participated in.
     * 3. Exclude topics they've already been recommended or posted in.
     * 4. Score each match by the share of the user's engagement in its
     *    category (relevance %) and log it so it isn't repeated.
     * 5. Fall back to popular topics for new/inactive users.
     * 6. Top up with popular topics when category matches run dry so the
     *    recommendation card never disappears for engaged users.
     *
     * Each returned topic carries transient `relevance_score` (0-100) and
     * `recommendation_reason` attributes for display (SDD Table 7).
     *
     * @param  int  $limit  Max recommendations to return
     * @return Collection
     */
    public function generateRecommendations(User $user, int $limit = 5)
    {
        // 1. Weight each category by how often the user has posted in it
        $engagementByCategory = Topic::whereIn('id', function ($q) use ($user) {
            $q->select('topic_id')
                ->from('posts')
                ->where('user_id', $user->id);
        })
            ->whereNotNull('category_id')
            ->pluck('category_id')
            ->countBy();

        $totalEngagement = $engagementByCategory->sum();
        $userEngagedCategoryIds = $engagementByCategory->keys()->toArray();

        // 2. If user hasn't engaged with anything, recommend popular topics
        if (empty($userEngagedCategoryIds)) {
            return $this->getPopularTopics($user, $limit);
        }

        // 3. Find topics in those categories the user hasn't interacted with
        $recommendations = Topic::whereIn('category_id', $userEngagedCategoryIds)
            ->where('status', 'active')
            ->when(! $user->isSystemAdmin(), fn ($q) => $q->whereIn('group_id', $user->accessibleGroupIds()))
            ->whereNotIn('id', function ($q) use ($user) {
                // Exclude topics user already posted in
                $q->select('topic_id')
                    ->from('posts')
                    ->where('user_id', $user->id);
            })
            ->whereNotIn('id', function ($q) use ($user) {
                // Exclude topics already recommended
                $q->select('topic_id')
                    ->from('recommendation_log')
                    ->where('user_id', $user->id);
            })
            ->with('creator')
            ->with('category')
            ->withCount('posts')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // 4. Score and log each recommendation so it isn't repeated.
        //    Relevance = share of the user's engagement in the topic's category
        //    (e.g. 14 of 20 engaged posts in Mathematics = 70%).
        foreach ($recommendations as $topic) {
            $topic->relevance_score = (int) round(
                ($engagementByCategory[$topic->category_id] ?? 0) / max(1, $totalEngagement) * 100,
            );
            $topic->recommendation_reason = 'Based on similar topics you engaged with';

            RecommendationLog::updateOrCreate(
                ['user_id' => $user->id, 'topic_id' => $topic->id],
                [
                    'group_id' => $topic->group_id,
                    'recommended_at' => now(),
                    'reason' => $topic->recommendation_reason,
                    'relevance_score' => $topic->relevance_score,
                ],
            );
        }

        // 5. Top up with popular topics once category-based matches run dry,
        //    so students and lecturers keep seeing recommendations too.
        if ($recommendations->count() < $limit) {
            $fillers = $this->getPopularTopics($user, $limit)
                ->reject(fn (Topic $topic) => $recommendations->contains('id', $topic->id))
                ->take($limit - $recommendations->count());

            $recommendations = $recommendations->concat($fillers);
        }

        return $recommendations;
    }

    /**
     * Fallback: return the most popular topics (most replies) when
     * the user hasn't engaged with enough categories yet.
     *
     * Popularity-based suggestions carry a modest relevance score (capped
     * at 50%) since they aren't matched to the user's own engagement.
     *
     * @return Collection
     */
    private function getPopularTopics(User $user, int $limit = 5)
    {
        $query = Topic::active()
            ->with('creator')
            ->with('category')
            ->withCount('posts');

        // System admins see popular topics across all groups;
        // regular users see only their accessible groups.
        if (! $user->isSystemAdmin()) {
            $query->whereIn('group_id', $user->accessibleGroupIds());
        }

        $topics = $query->orderBy('posts_count', 'desc')
            ->limit($limit)
            ->get();

        $maxReplies = max(1, (int) $topics->max('posts_count'));

        foreach ($topics as $topic) {
            $topic->relevance_score = max(10, (int) round($topic->posts_count / $maxReplies * 50));
            $topic->recommendation_reason = 'Popular in your group';
        }

        return $topics;
    }
}
