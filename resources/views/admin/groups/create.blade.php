@extends('layouts.app')

@section('title', 'Create Group')
@section('admin')

@section('content')
<div class="container" style="max-width: 600px;">
    <div class="card">
        <div class="card-header">Create New Group</div>

        <div class="card-body">
            <form method="POST" action="{{ route('admin.groups.store') }}">
                @csrf

                <div class="form-group">
                    <label for="group_name" class="form-label">Group Name *</label>
                    <input
                        type="text"
                        id="group_name"
                        name="group_name"
                        value="{{ old('group_name') }}"
                        placeholder="e.g., Computer Science 101"
                        class="form-control"
                        required
                        maxlength="100"
                    >
                    @error('group_name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    <small class="form-text">Maximum 100 characters</small>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        placeholder="Optional description for this group..."
                        class="form-control"
                        rows="4"
                        maxlength="500"
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    <small class="form-text">Maximum 500 characters</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Group</button>
                    <a href="{{ route('admin.groups.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
