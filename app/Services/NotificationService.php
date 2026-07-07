<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Send a notification to a specific user.
     *
     * Both the new column format (title, message) and the legacy data
     * array are populated so the notification is compatible with both
     * the new views and existing quiz-notification views.
     */
    public function sendToUser(User $user, string $title, string $message, string $type = 'info', array $extraData = []): Notification
    {
        $data = array_merge([
            'title' => $title,
            'message' => $message,
        ], $extraData);

        return Notification::create([
            'user_id' => $user->id,
            'group_id' => $user->group_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Send a notification to multiple users by their IDs.
     */
    public function sendToUsers(array $userIds, string $title, string $message, string $type = 'info', array $extraData = []): void
    {
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $this->sendToUser($user, $title, $message, $type, $extraData);
        }
    }

    /**
     * Send a notification to all users in a group.
     */
    public function sendToGroup(Group $group, string $title, string $message, string $type = 'info', array $extraData = []): void
    {
        $userIds = User::where('group_id', $group->id)->pluck('id')->toArray();
        $this->sendToUsers($userIds, $title, $message, $type, $extraData);
    }

    /**
     * Send an inactivity warning to a user.
     */
    public function sendInactivityWarning(User $user, int|string $daysInactive): void
    {
        $this->sendToUser(
            $user,
            'Inactivity Warning',
            "You haven't posted in {$daysInactive} days. Please re-engage with your group!",
            'warning',
        );
    }

    /**
     * Send a quiz announcement notification.
     */
    public function sendQuizAnnouncement(User $user, string $quizTitle, Carbon $startTime, array $quizData = []): void
    {
        $this->sendToUser(
            $user,
            'Quiz Announcement',
            "A new quiz '{$quizTitle}' is scheduled for {$startTime->format('M d, Y \\a\\t g:ia')}",
            'alert',
            $quizData,
        );
    }

    /**
     * Send a topic recommendation notification.
     */
    public function sendRecommendation(User $user, string $topicTitle, array $extraData = []): void
    {
        $this->sendToUser(
            $user,
            'Topic Recommendation',
            "We think you might be interested in: {$topicTitle}",
            'recommendation',
            $extraData,
        );
    }

    /**
     * Get the count of unread notifications for a user.
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Mark all unread notifications as read for a user.
     */
    public function markAllAsRead(User $user): void
    {
        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
