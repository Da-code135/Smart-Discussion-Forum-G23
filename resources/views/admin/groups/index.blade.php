@extends('layouts.app')

@section('title', 'Group Management')
@section('activeNav', 'admin-groups')
@section('admin')

@section('content')
<div class="page-stack">
    <div class="admin-header page-stack">
        <div class="admin-header__row">
            <div>
                <h1>Group management</h1>
                <p>Manage groups, member counts, and membership workflows.</p>
            </div>
            @if (auth()->user()->isSystemAdmin())
                <a href="{{ route('admin.groups.create') }}" class="btn btn-primary">Create group</a>
            @endif
        </div>

        <form method="GET" action="{{ route('admin.groups.index') }}" class="filter-section">
            <div class="form-group">
                <label for="search" class="form-label">Search by group name</label>
                <input type="text" id="search" name="search" value="{{ $search }}" placeholder="Search groups..." class="form-control">
            </div>

            <div class="form-group">
                <label for="sort_by" class="form-label">Sort by</label>
                <select id="sort_by" name="sort_by" class="form-control">
                    <option value="created_at" {{ $sort_by === 'created_at' ? 'selected' : '' }}>Newest first</option>
                    <option value="member_count" {{ $sort_by === 'member_count' ? 'selected' : '' }}>Most members</option>
                </select>
            </div>

            <div class="filter-button-group">
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Group name</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Members</th>
                    <th>Created by</th>
                    <th>Created date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr>
                        <td><strong>{{ $group->group_name }}</strong></td>
                        <td><span class="badge badge-secondary">{{ $group->group_type ?? '—' }}</span></td>
                        <td>{{ $group->description ? Str::limit($group->description, 50) : 'N/A' }}</td>
                        <td><span class="member-badge">{{ $group->users_count }} members</span></td>
                        <td>{{ $group->createdBy->full_name ?? 'Unknown' }}</td>
                        <td>{{ $group->created_at->format('M d, Y') }}</td>
                        <td>
                            <div class="action-buttons">
                                <a href="{{ route('admin.groups.members', $group) }}" class="btn btn-secondary btn-sm">Members</a>
                                @if (auth()->user()->isSystemAdmin() || auth()->user()->canAdminGroup($group))
                                    <a href="{{ route('admin.groups.edit', $group) }}" class="btn btn-primary btn-sm">Edit</a>
                                @endif
                                @if (auth()->user()->isSystemAdmin() && $group->group_name !== 'General')
                                    <form method="POST" action="{{ route('admin.groups.destroy', $group) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this group?')">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No groups found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $groups->appends(request()->query())->links() }}
    </div>
</div>
@endsection
