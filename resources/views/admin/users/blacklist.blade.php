@extends('layouts.app')

@section('title', 'Blacklist User: ' . $user->full_name)
@section('admin')

@section('content')
<div class="container" style="max-width: 500px;">

    {{-- Back link --}}
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary" style="font-size: 0.875rem;">&larr; Back to User</a>
    </div>

    <div class="card">
        <div class="card-header" style="background: #dc3545; color: white;">Blacklist User: {{ $user->full_name }}</div>

        <div class="card-body">
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                This will blacklist <strong>{{ $user->full_name }}</strong> ({{ $user->email }}) and set their account status to <span class="badge badge-danger">Blacklisted</span>.
            </p>

            <form method="POST" action="{{ route('admin.users.blacklist.store', $user) }}">
                @csrf

                <div class="form-group">
                    <label for="reason" class="form-label">Reason *</label>
                    <textarea
                        id="reason"
                        name="reason"
                        class="form-control"
                        rows="3"
                        required
                        maxlength="500"
                        placeholder="Explain why this user is being blacklisted..."
                    >{{ old('reason') }}</textarea>
                    @error('reason')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="duration_days" class="form-label">Duration (days)</label>
                    <input
                        type="number"
                        id="duration_days"
                        name="duration_days"
                        class="form-control"
                        min="1"
                        max="365"
                        value="{{ old('duration_days') }}"
                        placeholder="Leave empty for permanent blacklist"
                    >
                    @error('duration_days')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    <small class="form-text">Leave empty for a permanent blacklist. Maximum 365 days.</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to blacklist {{ $user->full_name }}? This will immediately restrict their access.')">
                        Blacklist User
                    </button>
                    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
