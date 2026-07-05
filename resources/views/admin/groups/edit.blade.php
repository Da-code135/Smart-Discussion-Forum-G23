@extends('layouts.app')

@section('title', 'Edit Group')
@section('admin')

@section('content')
<div class="container" style="max-width: 600px;">
    <div class="card">
        <div class="card-header">Edit Group: {{ $group->group_name }}</div>

        <div class="card-body">
            <form method="POST" action="{{ route('admin.groups.update', $group) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="group_name" class="form-label">Group Name *</label>
                    <input
                        type="text"
                        id="group_name"
                        name="group_name"
                        value="{{ old('group_name', $group->group_name) }}"
                        class="form-control"
                        required
                        maxlength="100"
                    >
                    @error('group_name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-control"
                        rows="4"
                        maxlength="500"
                    >{{ old('description', $group->description) }}</textarea>
                    @error('description')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="group_type" class="form-label">Group Type *</label>
                    <select id="group_type" name="group_type" class="form-control" required>
                        <option value="" disabled {{ old('group_type', $group->group_type) ? '' : 'selected' }}>— Select type —</option>
                        <option value="student" {{ (old('group_type', $group->group_type) === 'student') ? 'selected' : '' }}>Student</option>
                        <option value="lecturer" {{ (old('group_type', $group->group_type) === 'lecturer') ? 'selected' : '' }}>Lecturer</option>
                        <option value="sysadmin" {{ (old('group_type', $group->group_type) === 'sysadmin') ? 'selected' : '' }}>System Admin</option>
                    </select>
                    @error('group_type')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="{{ route('admin.groups.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>

            @if ($group->group_name !== 'General')
                <div class="danger-zone" style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #dc3545;">
                    <h3 style="color: #dc3545; margin-bottom: 1rem;">Danger Zone</h3>
                    <p>Once you delete a group, there is no going back. Please be certain.</p>

                    <form method="POST" action="{{ route('admin.groups.destroy', $group) }}" style="margin-top: 1rem;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you absolutely sure? This action cannot be undone.')">
                            Delete This Group
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
