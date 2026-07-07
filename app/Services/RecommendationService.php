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
     * 4. Log each recommendation so we don't repeat them.
     * 5. Fall back to popular topics for new/inactive users.
     *
     * @param  int  $limit  Max recommendations to return
     * @return Collection
     */
    public function generateRecommendations(User $user, int $limit = 5)
    {
        // 1. Find topic categories the user has engaged with
        $userEngagedCategoryIds = Topic::whereIn('id', function ($q) use ($user) {
            $q->select('topic_id')
                ->from('posts')
                ->where('user_id', $user->id);
        })
            ->whereNotNull('category_id')
            ->pluck('category_id')
            ->unique()
            ->toArray();

        // 2. If user hasn't engaged with anything, recommend popular topics
        if (empty($userEngagedCategoryIds)) {
            return $this->getPopularTopics($user, $limit);
        }

        // 3. Find topics in those categories the user hasn't interacted with
        $recommendations = Topic::whereIn('category_id', $userEngagedCategoryIds)
            ->where('status', 'active')
            ->when($user->group_id !== null, fn ($q) => $q->where('group_id', $user->group_id))
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

        // 4. Log each recommendation so they aren't repeated
        foreach ($recommendations as $topic) {
            RecommendationLog::updateOrCreate(
                ['user_id' => $user->id, 'topic_id' => $topic->id],
                [
                    'group_id' => $user->group_id,
                    'recommended_at' => now(),
                    'reason' => 'Based on similar topics you engaged with',
                ],
            );
        }

        return $recommendations;
    }

    /**
     * Fallback: return the most popular topics (most replies) when
     * the user hasn't engaged with enough categories yet.
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
        // regular users see only their own group.
        if ($user->group_id !== null) {
            $query->forGroup($user->group_id);
        }

        return $query->orderBy('posts_count', 'desc')
            ->limit($limit)
            ->get();
    }
}
