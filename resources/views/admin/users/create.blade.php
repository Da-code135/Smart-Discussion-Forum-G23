@extends('layouts.app')

@section('title', 'Create User')
@section('admin')

@section('content')
<div class="container" style="max-width: 600px;">
    <div class="card">
        <div class="card-header">Create New User</div>

        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf

                <div class="form-group">
                    <label for="full_name" class="form-label">Full Name *</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="{{ old('full_name') }}"
                        placeholder="e.g., John Smith"
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
                        value="{{ old('email') }}"
                        placeholder="e.g., john.smith@example.com"
                        class="form-control"
                        required
                        maxlength="100"
                    >
                    @error('email')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password *</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        required
                        minlength="8"
                    >
                    @error('password')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    <small class="form-text">Minimum 8 characters, with mixed case and at least one number</small>
                </div>

                <div class="form-group">
                    <label for="password_confirmation" class="form-label">Confirm Password *</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        class="form-control"
                        required
                        minlength="8"
                    >
                </div>

                <div class="form-group">
                    <label for="role_id" class="form-label">Role *</label>
                    <select
                        id="role_id"
                        name="role_id"
                        class="form-control"
                        required
                    >
                        <option value="">-- Select Role --</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                {{ $role->role_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('role_id')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="group_id" class="form-label">Group *</label>
                    <select
                        id="group_id"
                        name="group_id"
                        class="form-control"
                        required
                    >
                        <option value="">-- Select Group --</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" {{ old('group_id') == $group->id ? 'selected' : '' }}>
                                {{ $group->group_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('group_id')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create User</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection