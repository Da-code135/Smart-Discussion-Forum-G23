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

        @if ($notifications->count() === 0)
            <div class="empty-state">
                <span class="material-symbols-outlined" style="font-size: 40px;">notifications</span>
                <p>No notifications yet.</p>
            </div>
        @else
            <div class="notification-list">
                @foreach ($notifications as $notification)
                    <div class="notification-item {{ $notification->read_at ? '' : 'notification-item--unread' }}">
                        <div class="notification-item__indicator">
                            @if (!$notification->read_at)
                                <span class="notification-dot"></span>
                            @endif
                        </div>
                        <div class="notification-item__body">
                            <p class="notification-item__type">{{ str_replace('_', ' ', ucfirst($notification->type)) }}</p>
                            <p class="notification-item__meta">{{ $notification->created_at->diffForHumans() }}</p>
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
