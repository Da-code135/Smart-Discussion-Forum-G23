@extends('layouts.app')

@section('title', 'Notifications')
@section('activeNav', 'notifications')

@section('content')
<div class="page-shell">
    <div class="page-shell__main page-stack">
        <header class="page-header">
            <h1>Notifications</h1>
            <p>Stay up to date with activity in your groups.</p>
        </header>

        @if (session('success'))
            <div class="alert alert-success" role="alert">
                {{ session('success') }}
            </div>
        @endif

        @if ($notifications->count() === 0)
            <div class="empty-state">
                <span class="material-symbols-outlined" style="font-size: 40px;">notifications</span>
                <p>No notifications yet.</p>
            </div>
        @else
            <div class="page-stack">
                @foreach ($notifications as $notification)
                    @php
                        $link = '#';
                        $quizId = $notification->data['quiz_id'] ?? null;
                        if ($notification->type === 'quiz_live' && $quizId) {
                            $link = route('quizzes.attempt', $quizId);
                        } elseif (in_array($notification->type, ['quiz_announcement', 'quiz_reminder']) && $quizId) {
                            $link = route('quizzes.announcement', $quizId);
                        }

                        $icon = 'notifications';
                        $typeLabel = str_replace('_', ' ', ucfirst($notification->type));
                        if ($notification->type === 'quiz_announcement') {
                            $icon = 'campaign';
                        } elseif ($notification->type === 'quiz_live') {
                            $icon = 'play_circle';
                        } elseif ($notification->type === 'quiz_reminder') {
                            $icon = 'alarm';
                        }
                    @endphp
                    <div class="discussion-item">
                        <div class="notification-icon" style="position: relative; display: flex; align-items: center; padding: 0 12px;">
                            @if (!$notification->read_at)
                                <span style="position: absolute; top: 50%; left: 4px; width: 8px; height: 8px; border-radius: 50%; background: var(--app-accent, #0066cc); transform: translateY(-50%);"></span>
                            @endif
                            <span class="material-symbols-outlined" style="font-size: 28px; color: var(--app-text-secondary);">{{ $icon }}</span>
                        </div>
                        <div class="topic-row__body" style="flex: 1;">
                            <div class="discussion-meta">
                                <span style="font-weight: 600; color: var(--app-accent, #0066cc); text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;">{{ $typeLabel }}</span>
                                <span class="discussion-meta-dot"></span>
                                <span>{{ $notification->created_at->diffForHumans() }}</span>
                                @if (!$notification->read_at)
                                    <span class="badge badge-secondary" style="font-size: 11px; padding: 1px 8px;">New</span>
                                @endif
                            </div>
                            @if (in_array($notification->type, ['quiz_announcement', 'quiz_live', 'quiz_reminder']))
                                <h3 style="margin: 4px 0;">{{ $notification->data['title'] ?? 'Quiz' }}</h3>
                                <div class="discussion-meta">
                                    <span>{{ $notification->data['duration_minutes'] ?? '?' }} minutes</span>
                                    @if (($notification->data['lecturer_name'] ?? null))
                                        <span class="discussion-meta-dot"></span>
                                        <span>{{ $notification->data['lecturer_name'] }}</span>
                                    @endif
                                </div>
                            @else
                                <p class="topic-row__excerpt" style="margin-top: 4px;">{{ $notification->data['message'] ?? '' }}</p>
                            @endif
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; padding-right: 4px;">
                            @if ($link !== '#')
                                <a href="{{ $link }}" class="btn btn-secondary btn-sm" title="Open">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">arrow_forward</span>
                                </a>
                            @endif
                            @if (!$notification->read_at)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm" title="Mark as read">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">done</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pagination-shell">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
