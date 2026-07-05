<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
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
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByRaw('read_at IS NULL DESC')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $notifications,
        ], 200);
    }

    /**
     * N2: Mark a notification as read.
     *
     * POST /api/v1/notifications/{id}/read
     *
     * Only the notification owner can mark it as read.
     */
    public function markAsRead(Request $request, int $id)
    {
        $notification = Notification::findOrFail($id);

        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to update this notification.',
            ], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => $notification,
        ], 200);
    }
}
