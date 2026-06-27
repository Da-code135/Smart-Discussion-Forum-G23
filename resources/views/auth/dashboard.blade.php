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
            <p style="margin: 0;">
                <strong>Your email is not verified.</strong>
                <a href="{{ route('verify-email') }}">Click here to verify your email →</a>
            </p>
        </div>
    </section>
@endif

<div class="dashboard-content">
    <h1>Welcome, {{ Auth::user()->full_name }}!</h1>
    <p class="user-role">Role: <strong>{{ Auth::user()->role->role_name }}</strong></p>

    {{-- Role-Based Rendering --}}
    @if (Auth::user()->role->role_name === 'Administrator')
        <div class="role-section">
            <h2>Administrator Dashboard</h2>
            <p>You have access to moderation and user management tools.</p>

            <div class="action-buttons">
                <a href="{{ route('admin.users-index') }}">User Management</a>
                <a href="{{ route('admin.statistics') }}">View Statistics</a>
                <a href="{{ route('groups.index') }}">Group Management</a>
            </div>
        </div>
    </section>
@endif

@if ($isAdmin)
    {{-- Admin Quick Actions --}}
    <section class="mb-4">
        <div class="bento-card profile-form-card">
            <h2 class="headline-sm" style="font-size: 1.25rem; margin-bottom: 0.5rem;">Administrator Dashboard</h2>
            <p class="text-muted" style="margin-bottom: 1rem;">You have access to moderatiogitn and user management tools.</p>
            <div class="admin-actions">
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
            <h2 style="font-size: 1.25rem;">Recent Discussions</h2>
            <a href="{{ route('forum.index') }}" class="section-link">
                View all
                <span class="material-symbols-outlined" style="font-size: 1.125rem;">chevron_right</span>
            </a>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <a href="{{ route('forum.index') }}" class="discussion-item">
                <div>
                    <h3>Database Normalisation — Help needed</h3>
                    <div class="discussion-meta">
                        <span>Posted by Brian S.</span>
                        <span class="discussion-meta-dot"></span>
                        <span>8 replies</span>
                        <span class="discussion-meta-dot"></span>
                        <span>2 hours ago</span>
                    </div>
                </div>
                <span class="discussion-action">
                    View
                    <span class="material-symbols-outlined" style="font-size: 1.125rem;">arrow_forward</span>
                </span>
            </a>

            <a href="{{ route('forum.index') }}" class="discussion-item">
                <div>
                    <h3>Quiz 3 results are out!</h3>
                    <div class="discussion-meta">
                        <span>Posted by System</span>
                        <span class="discussion-meta-dot"></span>
                        <span>Performance report available</span>
                    </div>
                </div>
                <span class="discussion-action">
                    View
                    <span class="material-symbols-outlined" style="font-size: 1.125rem;">arrow_forward</span>
                </span>
            </a>
        </div>
    </section>

    {{-- Recommended for you --}}
    <section class="recommendations-section">
        <div class="recommendations-header">
            <span class="material-symbols-outlined">smart_toy</span>
            <h2 style="font-size: 1.25rem; margin: 0;">Recommended for you</h2>
        </div>

        <div class="recommendations-grid">
            <div class="recommendation-card recommendation-card--secondary">
                <div class="recommendation-glow"></div>
                <div>
                    <span class="badge badge-secondary">AI Insight</span>
                    <h4 style="font-size: 1.25rem; margin: 0.75rem 0 0.25rem;">Normalisation Forms</h4>
                    <p style="font-size: 0.875rem; color: rgba(88, 103, 75, 0.7); margin: 0;">Based on your recent activity in DBMS</p>
                </div>
                <div class="recommendation-footer">
                    <span style="font-size: 0.75rem; font-weight: 500; color: var(--secondary);">15 active members</span>
                    <a href="{{ route('forum.index') }}" class="recommendation-add-btn" aria-label="Join discussion">
                        <span class="material-symbols-outlined" style="color: var(--secondary);">add</span>
                    </a>
                </div>
            </div>

            <div class="recommendation-card recommendation-card--tertiary">
                <div class="recommendation-glow"></div>
                <div>
                    <span class="badge badge-tertiary">Trending</span>
                    <h4 style="font-size: 1.25rem; margin: 0.75rem 0 0.25rem;">SQL Joins Explained</h4>
                    <p style="font-size: 0.875rem; color: rgba(92, 58, 31, 0.7); margin: 0;">Trending in your Computer Science group</p>
                </div>
                <div class="recommendation-footer">
                    <span style="font-size: 0.75rem; font-weight: 500; color: var(--tertiary);">22 active members</span>
                    <a href="{{ route('forum.index') }}" class="recommendation-add-btn" aria-label="Join discussion">
                        <span class="material-symbols-outlined" style="color: var(--tertiary);">add</span>
                    </a>
                </div>
            </div>
        </div>
    </section>
@endif

{{-- Quick profile link --}}
<section class="mt-4">
    <a href="{{ route('profile.edit') }}" class="section-link">
        <span class="material-symbols-outlined" style="font-size: 1.125rem;">person</span>
        View or edit your profile
    </a>
</section>
@endsection
