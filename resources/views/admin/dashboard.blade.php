@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('activeNav', 'admin-dashboard')
@section('admin')

@section('content')
<div class="page-stack">
    <header class="page-header">
        <h1>Admin dashboard</h1>
        <p>Welcome, {{ auth()->user()->full_name }} — {{ auth()->user()->role->role_name }}</p>
    </header>

    <section class="dashboard-grid">
        <div class="dashboard-card">
            <h3>Total users</h3>
            <div class="number">{{ \App\Models\User::count() }}</div>
            <a href="{{ route('admin.users.index') }}" class="section-link">View users</a>
        </div>
        <div class="dashboard-card">
            <h3>Active users</h3>
            <div class="number">{{ \App\Models\User::where('account_status', 'active')->count() }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Warned users</h3>
            <div class="number">{{ \App\Models\User::where('account_status', 'warned')->count() }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Blacklisted users</h3>
            <div class="number">{{ \App\Models\User::where('account_status', 'blacklisted')->count() }}</div>
        </div>
    </section>

    <section class="card page-stack">
        <div>
            <h2>Management tools</h2>
            <p>Administrative pages remain table-oriented for faster scanning and bulk work.</p>
        </div>
        <div class="links-grid">
            <a href="{{ route('admin.users.index') }}" class="link-btn">User management</a>
            <a href="{{ route('admin.groups.index') }}" class="link-btn">Group management</a>
            <a href="{{ route('admin.warnings.index') }}" class="link-btn">Warnings</a>
            <a href="{{ route('admin.blacklist.index') }}" class="link-btn">Blacklist</a>
            <a href="{{ route('admin.audit-logs.index') }}" class="link-btn">Audit logs</a>
            @if (auth()->user()->isSystemAdmin())
                <a href="{{ route('admin.statistics.index') }}" class="link-btn">Platform statistics</a>
                <a href="{{ route('admin.group-statistics.index') }}" class="link-btn">Group statistics</a>
                <a href="{{ route('admin.system-config.index') }}" class="link-btn">System config</a>
                <a href="{{ route('admin.ip-whitelist.index') }}" class="link-btn">IP whitelist</a>
            @else
                <a href="{{ route('admin.statistics.index') }}" class="link-btn">Platform statistics</a>
            @endif
        </div>
    </section>
</div>
@endsection
