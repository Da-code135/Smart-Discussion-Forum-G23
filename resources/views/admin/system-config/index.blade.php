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

            <!-- Warning Response Days -->
            <div class="form-group">
                <label for="warning_response_days" class="form-label">Warning Response Days</label>
                <input
                    type="number"
                    id="warning_response_days"
                    name="warning_response_days"
                    value="{{ $configs->firstWhere('config_key', 'warning_response_days')->config_value ?? 7 }}"
                    min="1"
                    class="form-control"
                    required
                >
                <small class="form-text">Days a user has to respond to a warning before escalation</small>
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

            <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border-color, #e5e7eb);">

            <h3>Escalation Timing</h3>

            <!-- Days Before Second Warning -->
            <div class="form-group">
                <label for="days_before_second_warning" class="form-label">Days Before Second Warning</label>
                <input
                    type="number"
                    id="days_before_second_warning"
                    name="days_before_second_warning"
                    value="{{ $configs->firstWhere('config_key', 'days_before_second_warning')->config_value ?? 14 }}"
                    min="1"
                    class="form-control"
                    required
                >
                <small class="form-text">Days after Warning 1 before issuing Warning 2</small>
            </div>

            <!-- Days Before Blacklist -->
            <div class="form-group">
                <label for="days_before_blacklist" class="form-label">Days Before Blacklist</label>
                <input
                    type="number"
                    id="days_before_blacklist"
                    name="days_before_blacklist"
                    value="{{ $configs->firstWhere('config_key', 'days_before_blacklist')->config_value ?? 14 }}"
                    min="1"
                    class="form-control"
                    required
                >
                <small class="form-text">Days after Warning 2 before automatic blacklist</small>
            </div>

            <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border-color, #e5e7eb);">

            <h3>Quiz Settings</h3>

            <!-- Quiz Late Join Allowed -->
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input
                        type="checkbox"
                        name="quiz_late_join_allowed"
                        value="1"
                        {{ $configs->firstWhere('config_key', 'quiz_late_join_allowed')->config_value === '1' ? 'checked' : '' }}
                    >
                    Allow late joins for quizzes
                </label>
                <small class="form-text">If checked, students who join after the quiz start time receive the full duration</small>
            </div>

            <button type="submit" class="btn btn-primary">Save Configuration</button>
        </form>
    </div>
</div>
@endsection
