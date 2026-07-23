@extends('layouts.app')

@section('title', 'Dashboard')
@section('activeNav', 'home')

@section('content')
@php
    $user = Auth::user();
    $firstName = explode(' ', $user->full_name)[0];
    $isAdmin = $user->isAdmin();
    $avatarTone = ['var(--avatar-tone-1)', 'var(--avatar-tone-2)', 'var(--avatar-tone-3)', 'var(--avatar-tone-4)', 'var(--avatar-tone-5)'][$user->id % 5];
    $initials = collect(explode(' ', $user->full_name))->map(fn ($word) => strtoupper(substr($word, 0, 1)))->take(2)->join('');
@endphp

<div class="page-shell">
    <div class="page-shell__main page-stack">
        <header class="page-header">
            <div class="page-header-row">
                <div>
                    <h1>Welcome back, {{ $firstName }}</h1>
                    <p>
                        @if ($isAdmin)
                            Review users, groups, and platform activity from your admin workspace.
                        @else
                            Here is a summary of what is happening in your group today.
                        @endif
                    </p>
                </div>
            </div>
        </header>

        @if ($user->email_verified_at === null)
            <div class="alert alert-warning" role="alert">
                <span class="material-symbols-outlined">mail</span>
                <span><strong>Your email is not verified.</strong> <a href="{{ route('verify-email') }}">Verify it here.</a></span>
            </div>
        @endif

        {{-- Sort tabs (decorative) --}}
        <div class="sort-tabs">
            <span class="sort-tab is-active">Hot</span>
            <span class="sort-tab">New</span>
            <span class="sort-tab">Top</span>
        </div>

        {{-- Recent discussions as post cards --}}
        <section>
            @if (empty($recentTopics) || count($recentTopics) === 0)
                <div class="empty-state">
                    <span class="material-symbols-outlined" style="font-size: 40px;">forum</span>
                    <p>No discussions yet across the platform.</p>
                    <a href="{{ route('forum.create') }}" class="btn btn-primary">Start a topic</a>
                </div>
            @else
                <div class="topic-list">
                    @foreach ($recentTopics as $topic)
                        <article class="post-card">
                            <div class="post-card__content">
                                <div class="post-thumbnail" style="--avatar-bg: var(--avatar-tone-{{ ($topic['id'] % 5) + 1 }});">
                                    <span class="material-symbols-outlined">forum</span>
                                </div>
                                <div class="post-card__body">
                                    <a href="{{ route('forum.show', $topic['id']) }}" class="post-title">{{ $topic['title'] }}</a>
                                    <div class="post-meta">
                                        Posted by <strong>{{ $topic['creator_name'] }}</strong>
                                        <span class="post-meta-sep">·</span>
                                        {{ \Carbon\Carbon::parse($topic['created_at'])->diffForHumans() }}
                                    </div>
                                    <p class="post-excerpt">{{ $topic['reply_count'] }} {{ $topic['reply_count'] === 1 ? 'reply' : 'replies' }}</p>
                                    <div class="post-actions">
                                        <a href="{{ route('forum.show', $topic['id']) }}" class="post-action-btn">
                                            <span class="material-symbols-outlined">chat_bubble</span>
                                            {{ $topic['reply_count'] }} {{ $topic['reply_count'] === 1 ? 'Comment' : 'Comments' }}
                                        </a>
                                        <x-share-dropdown :topic="$topic" />
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

    </div>

    <aside class="page-shell__sidebar page-stack">

        {{-- Recommended topics (sidebar, Reddit-style) --}}
        @if (!empty($recommendedTopics) && count($recommendedTopics) > 0)
            <section class="sidebar-card">
                <div class="sidebar-card__header">
                    <span class="material-symbols-outlined">trending_up</span>
                    <h2>Recommended</h2>
                </div>
                <p style="font-size: 13px; color: var(--app-text-secondary); margin-bottom: 12px;">
                    Active conversations you may find useful.
                </p>
                <ul class="sidebar-recommendations">
                    @foreach ($recommendedTopics as $rec)
                        <li>
                            <a href="{{ route('forum.show', $rec['id']) }}" class="sidebar-recommendation-link">
                                <span class="sidebar-recommendation-title">{{ $rec['title'] }}</span>
                                <span class="sidebar-recommendation-meta">{{ $rec['member_count'] }} {{ $rec['member_count'] === 1 ? 'comment' : 'comments' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- User Hub --}}
        <section class="sidebar-card">
            <div class="sidebar-card__header">
                <span class="material-symbols-outlined">person</span>
                <h2>{{ $user->full_name }}</h2>
            </div>

            {{-- Status badges --}}
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;">
                <span class="status-badge status-{{ $user->account_status }}">{{ ucfirst($user->account_status) }}</span>
                <span class="badge badge-secondary">{{ $user->isSystemAdmin() ? 'Platform' : ($user->group?->group_name ?? 'General') }}</span>
            </div>

            {{-- Mini stats row --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; padding: 12px; background: var(--app-page-bg); border-radius: var(--radius-card);">
                <div>
                    <p style="font-size: 11px; color: var(--app-text-muted); margin: 0 0 2px;">Role</p>
                    <p style="font-size: 13px; font-weight: 600; margin: 0;">{{ $user->role->role_name }}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--app-text-muted); margin: 0 0 2px;">Last active</p>
                    <p style="font-size: 13px; font-weight: 600; margin: 0;">{{ $user->last_active_at ? $user->last_active_at->format('M d') : 'Never' }}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--app-text-muted); margin: 0 0 2px;">Status</p>
                    <p style="font-size: 13px; font-weight: 600; margin: 0;">{{ ucfirst($user->account_status) }}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--app-text-muted); margin: 0 0 2px;">Member since</p>
                    <p style="font-size: 13px; font-weight: 600; margin: 0;">{{ $user->created_at ? $user->created_at->format('M Y') : 'Recently' }}</p>
                </div>
            </div>

            {{-- Quick actions --}}
            <div style="display: grid; gap: 6px;">
                <a href="{{ route('forum.index') }}" class="btn btn-primary btn-block">Open forum</a>
                <a href="{{ route('forum.create') }}" class="btn btn-secondary btn-block">New topic</a>
                @if ($isAdmin)
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-block">Manage users</a>
                    <a href="{{ route('admin.groups.index') }}" class="btn btn-secondary btn-block">Manage groups</a>
                @endif
                <a href="{{ route('profile.edit') }}" class="btn btn-ghost btn-block">Edit profile</a>
            </div>
        </section>
    </aside>
</div>
@endsection
