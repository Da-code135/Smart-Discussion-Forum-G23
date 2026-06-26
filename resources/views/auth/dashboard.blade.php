@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
{{-- Email Verification Warning --}}
@if (Auth::user()->email_verified_at === null)
    <div class="alert alert-warning" role="alert">
        <strong>⚠️ Your email is not verified</strong><br>
        <a href="{{ route('verify-email') }}" style="color: inherit; font-weight: 600;">
            Click here to verify your email →
        </a>
    </div>
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
    @else
        <div class="role-section">
            <h2>Forum Dashboard</h2>
            <p>Welcome to {{ config('app.name') }}! Start exploring or create a new topic.</p>

    <div class="dashboard-content">
        <h1>Welcome, {{ Auth::user()->full_name }}!</h1>
        <p class="user-role">Role: <strong>{{ Auth::user()->role->role_name }}</strong></p>

        <!-- #79: ROLE-BASED RENDERING -->
        @if (Auth::user()->role->role_name === 'Administrator')
            <div class="role-section">
                <h2>Administrator Dashboard</h2>
                <p>You have access to moderation and user management tools.</p>

                <div class="action-buttons">
                     <a href="{{ route('admin.dashboard') }}">Admin Panel</a>
                    <a href="{{ route('admin.users-index') }}">User Management</a>
                    <a href="{{ route('admin.statistics') }}">View Statistics</a>
                    <a href="{{ route('groups.index') }}" class="link-btn">Group Management</a>
                </div>
            <div class="action-buttons">
                <a href="{{ route('forum.index') }}">Enter Forum</a>
                <a href="{{ route('profile.edit') }}">View Profile</a>
            </div>
        </div>
    @endif

    <div class="quick-stats">
        <div class="stat-card">
            <div class="stat-label">Account Status</div>
            <div class="stat-value {{ Auth::user()->account_status === 'active' ? 'status-active' : 'status-warned' }}">
                {{ ucfirst(Auth::user()->account_status) }}
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Last Active</div>
            <div class="stat-value">
                {{ Auth::user()->last_active_at ? Auth::user()->last_active_at->format('M d, Y H:i') : 'Never'}}
            </div>
        </div>
    </div>
</div>
@endsection
