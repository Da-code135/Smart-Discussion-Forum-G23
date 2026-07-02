@extends('layouts.app')

@section('title', 'Dashboard')
@section('activeNav', 'home')

@section('content')
@php
    $user = Auth::user();
    $firstName = explode(' ', $user->full_name)[0];
    $isAdmin = $user->role->role_name === 'Administrator';
@endphp

{{-- Welcome Header --}}
<header class="page-header">
    <h1>Welcome back, {{ $firstName }}</h1>
    <p>
        @if ($isAdmin)
            Manage users, groups, and platform activity from your admin dashboard.
        @else
            Here's what's happening in your groups
        @endif
    </p>
</header>

{{-- Email Verification Warning --}}
@if ($user->email_verified_at === null)
    <section class="mb-4">
        <div class="alert alert-warning alert-banner" role="alert">
            <span class="material-symbols-outlined">mail</span>
            <p class="!m-0">
                <strong>Your email is not verified.</strong>
                <a href="{{ route('verify-email') }}">Click here to verify your email →</a>
            </p>
        </div>
    </section>
@endif

{{-- Admin Quick Actions --}}
@if ($isAdmin)
    <section class="mb-4">
        <div class="bento-card profile-form-card">
            <h2 class="text-xl font-semibold mb-2">Administrator Dashboard</h2>
            <p class="text-[var(--on-surface-variant)] mb-4">You have access to moderation and user management tools.</p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.users.index') }}" class="btn btn-primary">User Management</a>
                <a href="{{ route('admin.groups.index') }}" class="btn btn-primary">Group Management</a>
                <a href="{{ route('groups.index') }}" class="btn btn-tertiary">All Groups</a>
            </div>
        </div>
    </section>
@endif

{{-- Quick Stats --}}
<section class="stats-grid">
    <div class="stat-card">
        <p class="stat-label">Account Status</p>
        <p class="stat-value stat-value--md status-{{ $user->account_status }}">
            {{ ucfirst($user->account_status) }}
        </p>
        <div class="stat-card-accent"></div>
    </div>
    <div class="stat-card">
        <p class="stat-label">Role</p>
        <p class="stat-value stat-value--md">{{ $user->role->role_name }}</p>
        <div class="stat-card-accent stat-card-accent--secondary"></div>
    </div>
    <div class="stat-card">
        <p class="stat-label">Last Active</p>
        <p class="stat-value stat-value--md">
            {{ $user->last_active_at ? $user->last_active_at->format('M d, Y') : 'Never' }}
        </p>
        <div class="stat-card-accent stat-card-accent--tertiary"></div>
    </div>
</section>

@if (!$isAdmin)
    {{-- Recent Discussions --}}
    <section class="discussions-section">
        <div class="section-header">
            <h2 class="text-xl font-semibold">Recent Discussions</h2>
            <a href="{{ route('forum.index') }}" class="section-link">
                View all
                <span class="material-symbols-outlined text-lg">chevron_right</span>
            </a>
        </div>

        @if (empty($recentTopics) || count($recentTopics) === 0)
            <div class="text-center py-10 opacity-70">
                <span class="material-symbols-outlined text-4xl mb-2 block opacity-40">forum</span>
                <p class="text-[var(--on-surface-variant)]">No discussions yet. <a href="{{ route('forum.create') }}" class="text-[var(--primary-sage)] font-medium">Start one →</a></p>
            </div>
        @else
            <div class="flex flex-col gap-4">
                @foreach ($recentTopics as $topic)
                    <a href="{{ route('forum.show', $topic['id']) }}" class="discussion-item">
                        <div>
                            <h3>{{ $topic['title'] }}</h3>
                            <div class="discussion-meta">
                                <span>Posted by {{ $topic['creator_name'] }}</span>
                                <span class="discussion-meta-dot"></span>
                                <span>{{ $topic['reply_count'] }} {{ $topic['reply_count'] === 1 ? 'reply' : 'replies' }}</span>
                                <span class="discussion-meta-dot"></span>
                                <span>{{ \Carbon\Carbon::parse($topic['created_at'])->diffForHumans() }}</span>
                            </div>
                        </div>
                        <span class="discussion-action">
                            View
                            <span class="material-symbols-outlined text-lg">arrow_forward</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Recommended for you --}}
    @if (!empty($recommendedTopics) && count($recommendedTopics) > 0)
    <section class="recommendations-section">
        <div class="recommendations-header">
            <span class="material-symbols-outlined">smart_toy</span>
            <h2 class="text-xl font-semibold !m-0">Recommended for you</h2>
        </div>

        <div class="recommendations-grid">
            @foreach ($recommendedTopics as $index => $rec)
                @php
                    $variant = $index === 0 ? 'secondary' : 'tertiary';
                    $badgeLabel = $index === 0 ? 'AI Insight' : 'Trending';
                    $colorVar = $index === 0 ? 'var(--secondary)' : 'var(--tertiary)';
                    $descColor = $index === 0 ? 'rgba(88,103,75,0.7)' : 'rgba(92,58,31,0.7)';
                @endphp
                <div class="recommendation-card recommendation-card--{{ $variant }}">
                    <div class="recommendation-glow"></div>
                    <div>
                        <span class="badge badge-{{ $variant }}">{{ $badgeLabel }}</span>
                        <h4 class="text-xl font-semibold mt-3 mb-1">{{ $rec['title'] }}</h4>
                        <p class="text-sm !m-0" style="color: {{ $descColor }};">Trending in your group</p>
                    </div>
                    <div class="recommendation-footer">
                        <span class="text-xs font-medium" style="color: {{ $colorVar }};">{{ $rec['member_count'] }} active members</span>
                        <a href="{{ route('forum.show', $rec['id']) }}" class="recommendation-add-btn" aria-label="Join discussion">
                            <span class="material-symbols-outlined" style="color: {{ $colorVar }};">add</span>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    @endif
@endif

{{-- Quick profile link --}}
<section class="mt-4">
    <a href="{{ route('profile.edit') }}" class="section-link">
        <span class="material-symbols-outlined text-lg">person</span>
        View or edit your profile
    </a>
</section>
@endsection
