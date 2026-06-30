@extends('layouts.app')

@section('title', 'System Configuration')
@section('admin')

@section('content')
<div class="container">
    <div class="admin-header">
        <h1>System Configuration</h1>
        <p>Manage system-wide settings and security parameters</p>
    </div>

    <div class="card" style="max-width: 600px;">
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.system-config.update') }}">
            @csrf
            @method('PUT')

            <!-- Max Login Attempts -->
            <div class="form-group">
                <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                <input
                    type="number"
                    id="max_login_attempts"
                    name="max_login_attempts"
                    value="{{ $configs->firstWhere('config_key', 'max_login_attempts')->config_value ?? 5 }}"
                    min="1"
                    class="form-control"
                    required
                >
                <small class="form-text">Number of failed login attempts before lockout</small>
            </div>

            <!-- Lockout Minutes -->
            <div class="form-group">
                <label for="lockout_minutes" class="form-label">Lockout Duration (minutes)</label>
                <input
                    type="number"
                    id="lockout_minutes"
                    name="lockout_minutes"
                    value="{{ $configs->firstWhere('config_key', 'lockout_minutes')->config_value ?? 15 }}"
                    min="1"
                    class="form-control"
                    required
                >
                <small class="form-text">How long to lock out user after max attempts</small>
            </div>

            <!-- Inactivity Warning Days -->
            <div class="form-group">
                <label for="inactivity_warning_days" class="form-label">Inactivity Warning (days)</label>
                <input
                    type="number"
                    id="inactivity_warning_days"
                    name="inactivity_warning_days"
                    value="{{ $configs->firstWhere('config_key', 'inactivity_warning_days')->config_value ?? 30 }}"
                    min="1"
                    class="form-control"
                    required
                >
                <small class="form-text">Days of inactivity before first warning</small>
            </div>

            <!-- Blacklist Duration -->
            <div class="form-group">
                <label for="blacklist_duration_days" class="form-label">Blacklist Duration (days)</label>
                <input
                    type="number"
                    id="blacklist_duration_days"
                    name="blacklist_duration_days"
                    value="{{ $configs->firstWhere('config_key', 'blacklist_duration_days')->config_value ?? 30 }}"
                    min="1"
                    class="form-control"
                    required
                >
                <small class="form-text">How long a user stays blacklisted</small>
            </div>

            <button type="submit" class="btn btn-primary">Save Configuration</button>
        </form>
    </div>
</div>
@endsection
