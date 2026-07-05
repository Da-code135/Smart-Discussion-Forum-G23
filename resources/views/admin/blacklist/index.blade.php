@extends('layouts.app')

@section('title', 'Blacklist')
@section('activeNav', 'admin-users')
@section('admin')

@section('content')
<div class="page-stack">
    <div class="admin-header page-stack">
        <div class="admin-header__row">
            <div>
                <h1>Blacklist</h1>
                <p>Review and lift blacklist records for members.</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="btn btn-primary">Go to users to blacklist someone</a>
        </div>

        <form method="GET" action="{{ route('admin.blacklist.index') }}" class="filter-section">
            <div class="form-group">
                <label for="search" class="form-label">Search by user name or email</label>
                <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Search blacklist..." class="form-control">
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
                    <th>Blacklisted at</th>
                    <th>Reason</th>
                    <th>Lifted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($blacklistRecords as $record)
                    <tr>
                        <td>
                            <strong>{{ $record->user->full_name }}</strong><br>
                            <span class="meta-text">{{ $record->user->email }}</span>
                        </td>
                        <td>{{ $record->blacklisted_at->format('M d, Y H:i') }}</td>
                        <td>{{ Str::limit($record->reason, 50) }}</td>
                        <td>
                            @if ($record->lifted_at)
                                <span class="badge badge-success">Yes</span>
                                <div class="meta-text">Lifted by {{ $record->liftedBy->full_name ?? 'Unknown' }}</div>
                            @else
                                <span class="badge badge-danger">No</span>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.blacklist.show', $record) }}" class="btn btn-secondary btn-sm">View</a>
                                @if (!$record->lifted_at)
                                    <form method="POST" action="{{ route('admin.blacklist.update', $record) }}">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit" class="btn btn-success btn-sm">Lift blacklist</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No blacklist records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $blacklistRecords->appends(request()->query())->links() }}
    </div>
</div>
@endsection
