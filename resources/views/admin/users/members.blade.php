@extends('layouts.app')

@section('title', 'Group Members')
@section('admin')

@section('content')
<div class="container" style="max-width: 800px;">
    <div class="card">
        <div class="card-header">{{ $group->group_name }} — Members</div>

        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <p class="form-text mb-4">
                {{ $group->users_count }} member(s) currently in this group
            </p>

            {{-- Membership Form --}}
            <form method="POST" action="{{ route('admin.groups.update-members', $group) }}">
                @csrf
                @method('PUT')

                {{-- Select All Checkbox --}}
                <div class="select-all-group" style="padding: 1rem; background: #f8f9fa; border-bottom: 2px solid #ddd; display: flex; align-items: center; justify-content: space-between;">
                    <label>
                        <input type="checkbox" id="select-all" style="width: 18px; height: 18px; cursor: pointer;">
                        <strong>Select All</strong>
                    </label>
                </div>

                {{-- Members List --}}
                <div class="members-list" style="max-height: 500px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 1rem; margin-bottom: 1.5rem;">
                    @forelse ($allUsers as $user)
                        <div class="member-item" style="display: flex; align-items: center; padding: 0.75rem; border-bottom: 1px solid #eee;">
                            <input
                                type="checkbox"
                                name="user_ids[]"
                                value="{{ $user->id }}"
                                class="member-checkbox"
                                style="margin-right: 1rem; width: 18px; height: 18px; cursor: pointer;"
                                {{ in_array($user->id, $memberIds) ? 'checked' : '' }}
                            >
                            <div class="member-info" style="flex: 1;">
                                <div class="member-name" style="font-weight: 600; color: #333;">{{ $user->full_name }}</div>
                                <div class="member-email" style="font-size: 0.875rem; color: #666;">{{ $user->email }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center">No users available</p>
                    @endforelse
                </div>

                <button type="submit" class="btn btn-primary btn-block">Update Members</button>
            </form>

            <div class="nav-buttons mt-4">
                <a href="{{ route('admin.groups.index') }}">Back to Groups</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('select-all').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.member-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
</script>
@endpush
