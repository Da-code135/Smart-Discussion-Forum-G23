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

    <section class="page-stack">
        <div class="page-header">
            <h2>Platform statistics</h2>
            <p>Group engagement metrics visualized &mdash; click <strong>Recalculate</strong> on any group to pull live data.</p>
        </div>

        @include('admin.statistics.partials.stats-content')
    </section>
</div>
@endsection
