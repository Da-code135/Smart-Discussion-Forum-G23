<?php

namespace App\Services;

use App\Models\ParticipationActivity;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\Topic;
use App\Models\User;

class ParticipationService
{
    /**
     * Award the daily login point. Only one award per user per calendar day,
     * so repeated logins on the same day are no-ops.
     */
    public function recordDailyLogin(User $user): void
    {
        ParticipationActivity::firstOrCreate(
            [
                'user_id' => $user->id,
                'activity_type' => ParticipationActivity::TYPE_DAILY_LOGIN,
                'activity_date' => today(),
            ],
            ['points' => $this->pointsFor(ParticipationActivity::TYPE_DAILY_LOGIN)]
        );
    }

    /**
     * Award points for creating a new discussion topic.
     */
    public function recordTopicCreated(User $user, Topic $topic): void
    {
        ParticipationActivity::firstOrCreate(
            [
                'user_id' => $user->id,
                'activity_type' => ParticipationActivity::TYPE_TOPIC_CREATED,
                'subject_type' => Topic::class,
                'subject_id' => $topic->id,
            ],
            [
                'points' => $this->pointsFor(ParticipationActivity::TYPE_TOPIC_CREATED),
                'activity_date' => today(),
            ]
        );
    }

    /**
     * Award points for replying to an existing topic.
     */
    public function recordReplyPosted(User $user, Post $post): void
    {
        ParticipationActivity::firstOrCreate(
            [
                'user_id' => $user->id,
                'activity_type' => ParticipationActivity::TYPE_REPLY_POSTED,
                'subject_type' => Post::class,
                'subject_id' => $post->id,
            ],
            [
                'points' => $this->pointsFor(ParticipationActivity::TYPE_REPLY_POSTED),
                'activity_date' => today(),
            ]
        );
    }

    /**
     * Award points for completing a quiz. Only one award per student per quiz,
     * so regrading an attempt never double-counts.
     */
    public function recordQuizCompleted(User $user, Quiz $quiz): void
    {
        ParticipationActivity::firstOrCreate(
            [
                'user_id' => $user->id,
                'activity_type' => ParticipationActivity::TYPE_QUIZ_COMPLETED,
                'subject_type' => Quiz::class,
                'subject_id' => $quiz->quiz_id,
            ],
            [
                'points' => $this->pointsFor(ParticipationActivity::TYPE_QUIZ_COMPLETED),
                'activity_date' => today(),
            ]
        );
    }

    /**
     * Configured point weight for an activity type.
     */
    public function pointsFor(string $activityType): float
    {
        return (float) config("participation.weights.{$activityType}", 0);
    }

    /**
     * Cumulative participation score for a single user.
     */
    public function totalFor(User $user): float
    {
        return (float) ParticipationActivity::where('user_id', $user->id)->sum('points');
    }

    /**
     * Per-activity breakdown for a single user.
     *
     * @return array<string, array{label: string, count: int, points: float}>
     */
    public function breakdownFor(User $user): array
    {
        $rows = ParticipationActivity::where('user_id', $user->id)
            ->selectRaw('activity_type, COUNT(*) as activity_count, SUM(points) as total_points')
            ->groupBy('activity_type')
            ->get()
            ->keyBy('activity_type');

        $breakdown = [];
        foreach (ParticipationActivity::TYPES as $type) {
            $row = $rows->get($type);
            $breakdown[$type] = [
                'label' => ParticipationActivity::label($type),
                'count' => $row ? (int) $row->activity_count : 0,
                'points' => $row ? (float) $row->total_points : 0.0,
            ];
        }

        return $breakdown;
    }

    /**
     * Bulk totals and per-type counts for a set of user IDs (avoids N+1).
     *
     * @param  array<int>  $userIds
     * @return array<int, array{total: float, counts: array<string, int>}>
     */
    public function summaryForUsers(array $userIds): array
    {
        $rows = ParticipationActivity::whereIn('user_id', $userIds)
            ->selectRaw('user_id, activity_type, COUNT(*) as activity_count, SUM(points) as total_points')
            ->groupBy('user_id', 'activity_type')
            ->get();

        $summary = [];
        foreach ($userIds as $id) {
            $summary[$id] = [
                'total' => 0.0,
                'counts' => array_fill_keys(ParticipationActivity::TYPES, 0),
            ];
        }

        foreach ($rows as $row) {
            $summary[$row->user_id]['total'] += (float) $row->total_points;
            $summary[$row->user_id]['counts'][$row->activity_type] = (int) $row->activity_count;
        }

        return $summary;
    }
}
