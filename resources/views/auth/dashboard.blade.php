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
            <h1>Welcome back, {{ $firstName }}</h1>
            <p>
                @if ($isAdmin)
                    Review users, groups, and platform activity from your admin workspace.
                @else
                    Here is a calm summary of what is happening in your group today.
                @endif
            </p>
        </header>

        @if ($user->email_verified_at === null)
            <div class="alert alert-warning" role="alert">
                <span class="material-symbols-outlined">mail</span>
                <span><strong>Your email is not verified.</strong> <a href="{{ route('verify-email') }}">Verify it here.</a></span>
            </div>
        @endif

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

        @if (!$isAdmin)
            <section class="card page-stack">
                <div class="section-header">
                    <div>
                        <h2>Recent discussions</h2>
                        <p>Latest threads in your group.</p>
                    </div>
                    <a href="{{ route('forum.index') }}" class="section-link">View all <span class="material-symbols-outlined">arrow_forward</span></a>
                </div>

                @if (empty($recentTopics) || count($recentTopics) === 0)
                    <div class="empty-state">
                        <span class="material-symbols-outlined" style="font-size: 40px;">forum</span>
                        <p>No discussions yet. Start the first topic for your group.</p>
                        <a href="{{ route('forum.create') }}" class="btn btn-primary">Start a topic</a>
                    </div>
                @else
                    <div class="topic-list">
                        @foreach ($recentTopics as $topic)
                            <a href="{{ route('forum.show', $topic['id']) }}" class="discussion-item">
                                <div class="topic-row__body">
                                    <h3>{{ $topic['title'] }}</h3>
                                    <div class="discussion-meta">
                                        <span>{{ $topic['creator_name'] }}</span>
                                        <span class="discussion-meta-dot"></span>
                                        <span>{{ $topic['reply_count'] }} {{ $topic['reply_count'] === 1 ? 'reply' : 'replies' }}</span>
                                        <span class="discussion-meta-dot"></span>
                                        <span>{{ \Carbon\Carbon::parse($topic['created_at'])->diffForHumans() }}</span>
                                    </div>
                                </div>
                                <span class="section-link">Open <span class="material-symbols-outlined">arrow_forward</span></span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            @if (!empty($recommendedTopics) && count($recommendedTopics) > 0)
                <section class="page-stack">
                    <div class="section-header">
                        <div>
                            <h2>Recommended for you</h2>
                            <p>Active conversations your group may find useful.</p>
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
        @endif
    </div>

    <aside class="page-shell__sidebar page-stack">
        <section class="sidebar-card page-stack">
            <div class="app-topbar-avatar" style="--avatar-bg: {{ $avatarTone }}; width: 72px; height: 72px; font-size: 24px;">{{ $initials }}</div>
            <div>
                <h2>{{ $user->full_name }}</h2>
                <p>{{ $user->role->role_name }}</p>
            </div>
            <div class="profile-summary-list">
                <span class="status-badge status-{{ $user->account_status }}">{{ ucfirst($user->account_status) }}</span>
                <span class="badge badge-secondary">{{ $user->group->group_name ?? 'General' }}</span>
            </div>
            <a href="{{ route('profile.edit') }}" class="btn btn-secondary btn-block">View profile</a>
        </section>
    </aside>
</div>
@endsection
