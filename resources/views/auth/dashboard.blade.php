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

        {{-- Stats row (kept but simplified) --}}
        <section class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Account status</p>
                <p class="stat-value stat-value--md">{{ ucfirst($user->account_status) }}</p>
                <div class="stat-card-accent"></div>
            </div>
            <div class="stat-card">
                <p class="stat-label">Role</p>
                <p class="stat-value stat-value--md">{{ $user->role->role_name }}</p>
                <div class="stat-card-accent stat-card-accent--secondary"></div>
            </div>
            <div class="stat-card">
                <p class="stat-label">Last active</p>
                <p class="stat-value stat-value--md">{{ $user->last_active_at ? $user->last_active_at->format('M d, Y') : 'Never' }}</p>
                <div class="stat-card-accent stat-card-accent--tertiary"></div>
            </div>
        </section>

        {{-- Quick actions --}}
        <section class="card page-stack">
            <div class="section-header">
                <div>
                    <h2>Quick actions</h2>
                    <p>Common tasks for your account and role.</p>
                </div>
            </div>
            <div class="form-actions-row">
                <a href="{{ route('forum.index') }}" class="btn btn-primary">Open forum</a>
                <a href="{{ route('forum.create') }}" class="btn btn-secondary">New topic</a>
                <a href="{{ route('profile.edit') }}" class="btn btn-ghost">Edit profile</a>
                @if ($isAdmin)
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Manage users</a>
                    <a href="{{ route('admin.groups.index') }}" class="btn btn-secondary">Manage groups</a>
                @endif
            </div>
        </section>

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

        {{-- Recommended topics --}}
        @if (!empty($recommendedTopics) && count($recommendedTopics) > 0)
            <section class="page-stack">
                <div class="section-header">
                    <div>
                        <h2>Recommended for you</h2>
                        <p>Active conversations across the platform you may find useful.</p>
                    </div>
                </div>
                <div class="recommendations-grid">
                    @foreach ($recommendedTopics as $index => $rec)
                        <div class="recommendation-card recommendation-card--{{ $index === 0 ? 'secondary' : 'tertiary' }}">
                            <span class="badge badge-secondary">{{ $index === 0 ? 'Suggested' : 'Trending' }}</span>
                            <div>
                                <h3>{{ $rec['title'] }}</h3>
                                <p>{{ $rec['member_count'] }} active members in this discussion.</p>
                            </div>
                            <div class="recommendation-footer">
                                <span class="meta-text">Join the conversation</span>
                                <a href="{{ route('forum.show', $rec['id']) }}" class="recommendation-add-btn" aria-label="Join discussion">
                                    <span class="material-symbols-outlined">arrow_forward</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    <aside class="page-shell__sidebar page-stack">
        <section class="sidebar-card">
            <div class="sidebar-card__header">
                <span class="material-symbols-outlined">person</span>
                <h2>{{ $user->full_name }}</h2>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <span class="status-badge status-{{ $user->account_status }}">{{ ucfirst($user->account_status) }}</span>
                <span class="badge badge-secondary">{{ $user->isSystemAdmin() ? 'Platform' : ($user->group?->group_name ?? 'General') }}</span>
            </div>
            <p style="margin-top: 8px; font-size: 13px;">{{ $user->role->role_name }} &middot; Member since {{ $user->created_at ? $user->created_at->format('M Y') : 'Recently' }}</p>
            <a href="{{ route('profile.edit') }}" class="btn btn-secondary btn-block" style="margin-top: 12px;">View profile</a>
        </section>
    </aside>
</div>
@endsection
