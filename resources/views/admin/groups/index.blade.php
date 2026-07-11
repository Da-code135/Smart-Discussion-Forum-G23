@extends('layouts.app')

@section('title', 'Group Management')
@section('admin')

@section('content')
<<<<<<< Updated upstream
<div class="container">
    <!-- Header with Create Button (System Admin only) -->
    <div class="admin-header">
        <h1>Group Management</h1>
        @if (auth()->user()->isSystemAdmin())
            <a href="{{ route('admin.groups.create') }}" class="btn btn-primary">
                + Create New Group
            </a>
        @endif
    </div>
=======
<div class="page-stack">
    <div class="admin-header page-stack">
        <div class="admin-header__row">
            <div>
                <h1>Group management</h1>
                <p>Manage groups, member counts, and membership workflows.</p>
            </div>
            @if (auth()->user()->isSystemAdmin())
                <a href="{{ route('admin.groups.create') }}" class="btn btn-primary">Create group</a>
                <a href="{{ route('admin.groups.trashed') }}" class="btn btn-secondary">Deleted groups</a>
            @endif
        </div>
>>>>>>> Stashed changes

    {{-- Search & Sort --}}
    <div class="filter-section">
        <form method="GET" action="{{ route('admin.groups.index') }}" class="filter-form">
            <div class="form-group">
                <label for="search" class="form-label">Search by Group Name</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search groups..."
                    class="form-control"
                >
            </div>

            <div class="form-group">
                <label for="sort_by" class="form-label">Sort By</label>
                <select id="sort_by" name="sort_by" class="form-control">
                    <option value="created_at" {{ $sort_by === 'created_at' ? 'selected' : '' }}>
                        Newest First
                    </option>
                    <option value="member_count" {{ $sort_by === 'member_count' ? 'selected' : '' }}>
                        Most Members
                    </option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
    </div>

    {{-- Groups Table --}}
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Group Name</th>
                    <th>Description</th>
                    <th>Members</th>
                    <th>Created By</th>
                    <th>Created Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr>
                        <td>
                            <strong>{{ $group->group_name }}</strong>
                        </td>
                        <td>
                            {{ $group->description ? Str::limit($group->description, 50) : 'N/A' }}
                        </td>
                        <td>
                            <span class="member-badge">{{ $group->users_count }} members</span>
                        </td>
                        <td>
                            {{ $group->createdBy->full_name ?? 'Unknown' }}
                        </td>
                        <td>
                            {{ $group->created_at->format('M d, Y') }}
                        </td>
                        <td>
                            <div class="action-buttons">
                                {{-- Manage Members (all admins) --}}
                                <a href="{{ route('admin.groups.members', $group) }}" class="btn btn-warning btn-sm">
                                    👥 Members
                                </a>

                                {{-- Edit Group (System Admin or Group Admin of this group) --}}
                                @if (auth()->user()->isSystemAdmin() || auth()->user()->canAdminGroup($group))
                                    <a href="{{ route('admin.groups.edit', $group) }}" class="btn btn-primary btn-sm">
                                        Edit
                                    </a>
                                @endif

                                {{-- Delete Group (System Admin only, cannot delete General) --}}
                                @if (auth()->user()->isSystemAdmin() && $group->group_name !== 'General')
                                    <form method="POST" action="{{ route('admin.groups.destroy', $group) }}" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this group?')">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem;">
                            No groups found
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="pagination">
        {{ $groups->appends(request()->query())->links() }}
    </div>
</div>
@endsection
