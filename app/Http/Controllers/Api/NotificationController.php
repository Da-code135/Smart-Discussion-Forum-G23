<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * N1: List authenticated user's notifications.
     *
     * GET /api/v1/me/notifications
     *
     * Returns paginated notifications, unread first, then by recent.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByRaw('read_at IS NULL DESC')
            ->orderByDesc('created_at')
            ->paginate(20);

        // Transform to include title and message alongside the legacy data field
        $notifications->getCollection()->transform(function (Notification $notification) {
            return [
                'id' => $notification->id,
                'user_id' => $notification->user_id,
                'group_id' => $notification->group_id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => $notification->data,
                'is_read' => $notification->read_at !== null,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
                'updated_at' => $notification->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications->items(),
            ],
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * N2: Mark a single notification as read.
     *
     * POST /api/v1/notifications/{id}/read
     *
     * Only the notification owner can mark it as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => [
                'id' => $notification->id,
                'read_at' => $notification->fresh()->read_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * N3: Mark all notifications as read for the authenticated user.
     *
     * POST /api/v1/notifications/read-all
     */
    public function readAll(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'data' => [
                'marked_count' => $count,
            ],
        ]);
    }

    /**
     * N4: Delete a single notification.
     *
     * DELETE /api/v1/notifications/{id}
     *
     * Only the notification owner can delete it.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
        ]);
    }

    /**
     * N5: Get the unread notification count for the authenticated user.
     *
     * GET /api/v1/me/notifications/unread-count
     *
     * Lightweight endpoint — returns just the count, useful for polling
     * the badge/icon in a frontend.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }
}
