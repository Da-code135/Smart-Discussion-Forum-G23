@extends('layouts.app')

@section('title', 'Warnings')
@section('activeNav', 'admin-users')
@section('admin')

@section('content')
<div class="page-stack">
    <div class="admin-header page-stack">
        <div class="admin-header__row">
            <div>
                <h1>Warnings</h1>
                <p>Track and resolve warnings issued to members.</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="btn btn-primary">Go to users to issue a warning</a>
        </div>

        <form method="GET" action="{{ route('admin.warnings.index') }}" class="filter-section">
            <div class="form-group">
                <label for="search" class="form-label">Search by user name or email</label>
                <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Search warnings..." class="form-control">
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
                    <th>User</th>
                    <th>Warning #</th>
                    <th>Reason</th>
                    <th>Issued by</th>
                    <th>Issued at</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($warnings as $warning)
                    <tr>
                        <td>
                            <strong>{{ $warning->user->full_name }}</strong><br>
                            <span class="meta-text">{{ $warning->user->email }}</span>
                        </td>
                        <td>{{ $warning->warning_number }}</td>
                        <td>{{ Str::limit($warning->reason, 50) }}</td>
                        <td>{{ $warning->createdBy->full_name ?? 'System' }}</td>
                        <td>{{ $warning->created_at->format('M d, Y H:i') }}</td>
                        <td>
                            @if ($warning->is_resolved)
                                <span class="badge badge-success">Resolved</span>
                            @else
                                <span class="badge badge-warning">Pending</span>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.warnings.show', $warning) }}" class="btn btn-secondary btn-sm">View</a>
                                @if (!$warning->is_resolved)
                                    <form method="POST" action="{{ route('admin.warnings.update', $warning) }}">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit" class="btn btn-success btn-sm">Resolve</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No warnings found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $warnings->appends(request()->query())->links() }}
    </div>
</div>
@endsection
