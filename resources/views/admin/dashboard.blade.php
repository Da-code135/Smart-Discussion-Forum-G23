@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('admin')

@section('content')
<div class="container">
    <div class="dashboard-header">
        <h1>Admin Dashboard</h1>
        <p>Welcome, {{ auth()->user()->full_name }} — {{ auth()->user()->role->role_name }}</p>
    </div>

    <!-- QUICK STATS -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3>Total Users</h3>
            <div class="number">{{ \App\Models\User::count() }}</div>
            <a href="{{ route('admin.users.index') }}">View Users</a>
        </div>

        <div class="dashboard-card">
            <h3>Active Users</h3>
            <div class="number">{{ \App\Models\User::where('account_status', 'active')->count() }}</div>
        </div>

        <div class="dashboard-card">
            <h3>Warned Users</h3>
            <div class="number">{{ \App\Models\User::where('account_status', 'warned')->count() }}</div>
        </div>

        <div class="dashboard-card">
            <h3>Blacklisted Users</h3>
            <div class="number">{{ \App\Models\User::where('account_status', 'blacklisted')->count() }}</div>
        </div>
    </div>

    <!-- ADMIN LINKS -->
    <div class="admin-links">
        <h2>Management Tools</h2>
        <div class="links-grid">
            {{-- User Management - All admins --}}
            <a href="{{ route('admin.users.index') }}" class="link-btn">
                👥 User Management
            </a>

            {{-- Group Management - All admins --}}
            <a href="{{ route('admin.groups.index') }}" class="link-btn">
                📁 Group Management
            </a>

            {{-- Moderation Panel - All admins --}}
            <a href="{{ route('admin.moderation.index') }}" class="link-btn">
                🛡️ Moderation Panel
            </a>

            {{-- Audit Logs - All admins --}}
            <a href="{{ route('admin.audit-logs.index') }}" class="link-btn">
                📋 Audit Logs
            </a>

            {{-- System Admin only links --}}
            @if (auth()->user()->isSystemAdmin())
                <a href="{{ route('admin.group-statistics.index') }}" class="link-btn">
                    📊 Group Statistics
                </a>

                <a href="{{ route('admin.system-config.index') }}" class="link-btn">
                    ⚙️ System Configuration
                </a>

                <a href="{{ route('admin.ip-whitelist.index') }}" class="link-btn">
                    🔒 IP Whitelist
                </a>
            @endif

            {{-- Back to regular dashboard --}}
            <a href="{{ route('dashboard') }}" class="link-btn">
                🏠 Back to Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
