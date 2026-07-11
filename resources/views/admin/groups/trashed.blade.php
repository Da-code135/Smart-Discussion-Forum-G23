@extends('layouts.app')

@section('title', 'Deleted Groups')
@section('activeNav', 'admin-groups')
@section('admin')

@section('content')
<div class="page-stack">
    <div class="admin-header page-stack">
        <div class="admin-header__row">
            <div>
                <h1>Deleted Groups</h1>
                <p>Groups that have been deleted can be restored here.</p>
            </div>
            <div class="action-buttons">
                <a href="{{ route('admin.groups.index') }}" class="btn btn-primary">Back to Groups</a>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.groups.trashed') }}" class="filter-section">
            <div class="form-group">
                <label for="search" class="form-label">Search by group name</label>
                <input type="text" id="search" name="search" value="{{ $search }}" placeholder="Search deleted groups..." class="form-control">
            </div>

            <div class="filter-button-group">
                <button type="submit" class="btn btn-primary">Search</button>
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
                    <th>Deleted date</th>
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
                        <td>{{ $group->deleted_at->format('M d, Y') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.groups.restore', $group) }}">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm"
                                    onclick="return confirm('Restore this group? All members and data will reappear.')">
                                    Restore
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No deleted groups found.</td>
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
