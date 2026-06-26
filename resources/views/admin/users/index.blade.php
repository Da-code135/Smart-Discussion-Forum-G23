@extends('layouts.app')

@section('title', 'User Management')
@section('admin')

@section('content')
<div class="admin-header">
    <h1>User Management</h1>
    <p>Manage users, roles, blacklists, and account statuses</p>

    {{-- Search & Filter Section --}}
    <form method="GET" action="{{ route('admin.users.index') }}">
        <div class="filter-section">
            {{-- Search --}}
            <div class="form-group">
                <label for="search" class="form-label">Search by Name or Email</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search users..."
                    class="form-control"
                >
            </div>

            {{-- Filter by Account Status --}}
            <div class="form-group">
                <label for="account_status" class="form-label">Filter by Status</label>
                <select id="account_status" name="account_status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="active" {{ $account_status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="warned" {{ $account_status === 'warned' ? 'selected' : '' }}>Warned</option>
                    <option value="blacklisted" {{ $account_status === 'blacklisted' ? 'selected' : '' }}>Blacklisted</option>
                </select>
            </div>

            {{-- Filter by Role --}}
            <div class="form-group">
                <label for="role" class="form-label">Filter by Role</label>
                <select id="role" name="role" class="form-control">
                    <option value="">All Roles</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r->id }}" {{ $role == $r->id ? 'selected' : '' }}>
                            {{ $r->role_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Buttons --}}
            <div class="filter-button-group">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Clear</a>
            </div>
        </div>
    </form>
</div>

{{-- User Table --}}
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Group</th>
                <th>Last Active</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>
                        <strong>{{ $user->full_name }}</strong>
                        @if ($user->email_verified_at)
                            <span class="badge badge-success" style="margin-left: 0.5rem; font-size: 0.75rem;">✓</span>
                        @endif
                    </td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <span class="role-badge">{{ $user->role->role_name }}</span>
                    </td>
                    <td>
                        <span class="status-badge status-{{ $user->account_status }}">
                            {{ ucfirst($user->account_status) }}
                        </span>
                    </td>
                    <td>{{ $user->group ? $user->group->group_name : '—' }}</td>
                    <td>
                        {{ $user->last_active_at ? $user->last_active_at->format('M d, Y H:i') : 'Never' }}
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-primary btn-sm">Edit</a>
                            <a href="{{ route('admin.users.members', $user->id) }}" class="btn btn-secondary btn-sm">View Groups</a>
                            
                            @if ($user->account_status === 'active')
                                <form method="POST" action="{{ route('admin.users.warn', $user->id) }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Warn this user?')">Warn</button>
                                </form>
                            @endif
                            
                            @if ($user->account_status === 'warned')
                                <form method="POST" action="{{ route('admin.users.blacklist', $user->id) }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Blacklist this user?')">Blacklist</button>
                                </form>
                            @endif
                            
                            @if ($user->account_status === 'blacklisted')
                                <form method="POST" action="{{ route('admin.users.activate', $user->id) }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Activate this user?')">Activate</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 3rem; color: var(--text-muted);">
                        No users found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Pagination --}}
<div class="pagination">
    {{ $users->appends(['search' => $search, 'account_status' => $account_status, 'role' => $role])->links() }}
</div>
@endsection
