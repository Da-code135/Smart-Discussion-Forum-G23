@extends('layouts.app')

@section('title', 'Edit User: ' . $user->full_name)
@section('admin')

@section('content')
<div class="container" style="max-width: 600px;">

    {{-- Back link --}}
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary" style="font-size: 0.875rem;">&larr; Back to User</a>
    </div>

    <div class="card">
        <div class="card-header">Edit User: {{ $user->full_name }}</div>

        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="full_name" class="form-label">Full Name *</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="{{ old('full_name', $user->full_name) }}"
                        class="form-control"
                        required
                        maxlength="100"
                    >
                    @error('full_name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address *</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $user->email) }}"
                        class="form-control"
                        required
                        maxlength="100"
                    >
                    @error('email')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Role dropdown: System Admin only --}}
                @if (auth()->user()->isSystemAdmin())
                    <div class="form-group">
                        <label for="role_id" class="form-label">Role *</label>
                        <select
                            id="role_id"
                            name="role_id"
                            class="form-control"
                            required
                        >
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                                    {{ $role->role_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_id')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>
                @else
                    {{-- Group Admin sees current role as read-only --}}
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="{{ $user->role->role_name }}" disabled>
                        <small class="form-text">Only System Administrators can change roles</small>
                    </div>
                @endif

                <div class="form-group">
                    <label for="group_id" class="form-label">Group *</label>
                    <select
                        id="group_id"
                        name="group_id"
                        class="form-control"
                        required
                    >
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" {{ old('group_id', $user->group_id) == $group->id ? 'selected' : '' }}>
                                {{ $group->group_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('group_id')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Account Status: System Admin only --}}
                @if (auth()->user()->isSystemAdmin())
                    <div class="form-group">
                        <label for="account_status" class="form-label">Account Status *</label>
                        <select
                            id="account_status"
                            name="account_status"
                            class="form-control"
                            required
                        >
                            <option value="active" {{ old('account_status', $user->account_status) === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="warned" {{ old('account_status', $user->account_status) === 'warned' ? 'selected' : '' }}>Warned</option>
                            <option value="blacklisted" {{ old('account_status', $user->account_status) === 'blacklisted' ? 'selected' : '' }}>Blacklisted</option>
                        </select>
                        @error('account_status')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>
                @else
                    {{-- Group Admin sees current status as read-only --}}
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <input type="text" class="form-control" value="{{ ucfirst($user->account_status) }}" disabled>
                        <small class="form-text">Only System Administrators can change account status</small>
                    </div>
                @endif

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>

            {{-- Danger Zone: System Admin only, cannot delete yourself --}}
            @if (auth()->user()->isSystemAdmin() && $user->id !== auth()->id())
                <div class="danger-zone" style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #dc3545;">
                    <h3 style="color: #dc3545; margin-bottom: 1rem;">Danger Zone</h3>
                    <p>Once you delete a user account, there is no going back. All associated data (warnings, blacklist records, agreements) will be permanently removed.</p>

                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" style="margin-top: 1rem;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you absolutely sure? This will permanently delete {{ $user->full_name }}\'s account. This action cannot be undone.')">
                            Delete This User
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
