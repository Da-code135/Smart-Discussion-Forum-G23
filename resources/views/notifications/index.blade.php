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
            <div class="notification-list">
                @foreach ($notifications as $notification)
                    @php
                        $link = '#';
                        $quizId = $notification->data['quiz_id'] ?? null;
                        if ($notification->type === 'quiz_live' && $quizId) {
                            $link = route('quizzes.attempt', $quizId);
                        } elseif (in_array($notification->type, ['quiz_announcement', 'quiz_reminder']) && $quizId) {
                            $link = route('quizzes.announcement', $quizId);
                        }
                    @endphp
                    <a href="{{ $link }}" class="notification-item {{ $notification->read_at ? '' : 'notification-item--unread' }}">
                        <div class="notification-item__indicator">
                            @if (!$notification->read_at)
                                <span class="notification-dot"></span>
                            @endif
                        </div>
                        <div class="notification-item__body">
                            <p class="notification-item__type">{{ str_replace('_', ' ', ucfirst($notification->type)) }}</p>
                            @if ($notification->type === 'quiz_announcement' || $notification->type === 'quiz_live' || $notification->type === 'quiz_reminder')
                                <p class="notification-item__preview">{{ $notification->data['title'] ?? 'A quiz' }} &mdash; {{ $notification->data['duration_minutes'] ?? '?' }} min</p>
                            @endif
                            <p class="notification-item__meta">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                        @if (!$notification->read_at)
                            <div class="notification-item__action" onclick="event.stopPropagation()">
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm" title="Mark as read">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">done</span>
                                    </button>
                                </form>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="pagination-shell">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
