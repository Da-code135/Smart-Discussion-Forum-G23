@extends('layouts.app')

@section('title', 'User Management')
@section('activeNav', 'admin-users')
@section('admin')

@section('content')
<div class="page-stack">
    <div class="admin-header page-stack">
        <div class="admin-header__row">
            <div>
                <h1>User management</h1>
                <p>Manage users, roles, blacklists, and account statuses.</p>
            </div>
            @if (auth()->user()->isSystemAdmin())
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Create user</a>
            @endif
        </div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="form-stack">
            <div class="filter-section">
                <div class="form-group">
                    <label for="search" class="form-label">Search by name or email</label>
                    <input type="text" id="search" name="search" value="{{ $search }}" placeholder="Search users..." class="form-control">
                </div>

                <div class="form-group">
                    <label for="account_status" class="form-label">Filter by status</label>
                    <select id="account_status" name="account_status" class="form-control">
                        <option value="">All statuses</option>
                        <option value="active" {{ $account_status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="warned" {{ $account_status === 'warned' ? 'selected' : '' }}>Warned</option>
                        <option value="blacklisted" {{ $account_status === 'blacklisted' ? 'selected' : '' }}>Blacklisted</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="role" class="form-label">Filter by role</label>
                    <select id="role" name="role" class="form-control">
                        <option value="">All roles</option>
                        @foreach ($roles as $r)
                            <option value="{{ $r->id }}" {{ $role == $r->id ? 'selected' : '' }}>{{ $r->role_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-button-group">
                    <button type="submit" class="btn btn-primary">Apply filters</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>

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
                    <th>Last active</th>
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
                                <span class="badge badge-success">Verified</span>
                            @endif
                            @if ($user->id === auth()->id())
                                <span class="badge badge-secondary">You</span>
                            @endif
                        </td>
                        <td>{{ $user->email }}</td>
                        <td><span class="role-badge">{{ $user->role->role_name }}</span></td>
                        <td><span class="status-badge status-{{ $user->account_status }}">{{ ucfirst($user->account_status) }}</span></td>
                        <td>{{ $user->group ? $user->group->group_name : '—' }}</td>
                        <td>{{ $user->last_active_at ? $user->last_active_at->format('M d, Y H:i') : 'Never' }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary btn-sm">View</a>
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary btn-sm">Edit</a>

                                @if (auth()->user()->isSystemAdmin())
                                    <a href="{{ route('admin.users.reset-password', $user) }}" class="btn btn-secondary btn-sm">Password</a>
                                    @if ($user->account_status !== 'blacklisted')
                                        <a href="{{ route('admin.users.blacklist', $user) }}" class="btn btn-danger btn-sm">Blacklist</a>
                                    @endif
                                @endif

                                @if ($user->account_status === 'blacklisted')
                                    <form method="POST" action="{{ route('admin.users.lift-blacklist', $user) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Lift blacklist for this user?')">Lift blacklist</button>
                                    </form>
                                @endif

                                @if (auth()->user()->isSystemAdmin() && $user->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.change-role', $user) }}" class="table-actions">
                                        @csrf
                                        <select name="role_id" class="form-control" style="width: auto; min-width: 140px;">
                                            @foreach ($roles as $r)
                                                <option value="{{ $r->id }}" {{ $user->role_id == $r->id ? 'selected' : '' }}>{{ $r->role_name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Change role for this user?')">Change role</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $users->appends(['search' => $search, 'account_status' => $account_status, 'role' => $role])->links() }}
    </div>
</div>
@endsection
