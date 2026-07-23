<?php

namespace App\Utilities;

use App\Models\Post;
use App\Models\Report;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Shared utility for content reporting used by both web and API controllers.
 *
 * Ensures consistent group isolation enforcement and report creation
 * regardless of the interface (web form or desktop client).
 */
class ReportUtility
{
    /**
     * The content types that can be reported.
     */
    const TYPE_TOPIC = 'topic';

    const TYPE_POST = 'post';

    const TYPE_REPLY = 'reply';

    /**
     * Create a new report.
     *
     * @param  User  $reporter  The user submitting the report
     * @param  string  $type  One of 'topic', 'post', 'reply'
     * @param  int  $id  The ID of the content being reported
     * @param  string  $reason  The reason for the report
     *
     * @throws ModelNotFoundException
     * @throws \RuntimeException When group isolation is violated
     */
    public function createReport(User $reporter, string $type, int $id, string $reason): Report
    {
        $class = match ($type) {
            self::TYPE_TOPIC => Topic::class,
            self::TYPE_POST, self::TYPE_REPLY => Post::class,
        };

        /** @var Model|Topic|Post $model */
        $model = $class::findOrFail($id);

        // Group isolation: only report content in the user's own accessible groups
        $this->enforceGroupIsolation($reporter, $model);

        $report = new Report([
            'reason' => $reason,
            'user_id' => $reporter->id,
        ]);

        $model->reports()->save($report);

        // Flag the reported content so the moderation panel can find it.
        // The topics status enum only allows 'active' or 'archived', so we
        // only flag Posts here. Topics are reported via the reports table
        // and handled by the moderation panel separately.
        if ($model instanceof Post) {
            $model->update(['is_reported' => true]);
        }

        return $report;
    }

    /**
     * Get paginated reports for a user.
     *
     * @return LengthAwarePaginator
     */
    public function getUserReports(User $user, int $perPage = 20)
    {
        return Report::where('user_id', $user->id)
            ->with('reportable')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Enforce that the reporter can only report content within their group.
     *
     * @throws \RuntimeException
     */
    private function enforceGroupIsolation(User $reporter, Model $model): void
    {
        // System admins can report anything (moderation visibility)
        if ($reporter->isSystemAdmin()) {
            return;
        }

        if (method_exists($model, 'group') && $model->group) {
            if (! $reporter->canAccessGroup($model->group->id)) {
                throw new \RuntimeException('You cannot report content outside your group.');
            }
        } elseif (isset($model->topic) && $model->topic instanceof Topic) {
            // Posts inherit group via their topic
            if (! $reporter->canAccessGroup($model->topic->group_id)) {
                throw new \RuntimeException('You cannot report content outside your group.');
            }
        }
    }
}
