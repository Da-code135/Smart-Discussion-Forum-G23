@extends('admin.layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Blacklist</h1>
        <a href="{{ route('admin.blacklist.create') }}" class="btn btn-primary">Add to Blacklist</a>
    </div>

    <!-- Search Form -->
    <form method="GET" action="{{ route('admin.blacklist.index') }}" class="mb-4">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search blacklist..." value="{{ request('search') }}">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </div>
    </form>

    <!-- Blacklist Table -->
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Blacklisted At</th>
                    <th>Reason</th>
                    <th>Lifted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($blacklistRecords as $record)
                    <tr>
                        <td>{{ $record->user->full_name }}<br><small>{{ $record->user->email }}</small></td>
                        <td>{{ $record->blacklisted_at->format('Y-m-d H:i') }}</td>
                        <td>{{ Str::limit($record->reason, 50) }}</td>
                        <td>
                            @if($record->lifted_at)
                                <span class="badge bg-success">Yes</span>
                                <br>
                                <small>Lifted by: {{ $record->liftedBy->full_name }}</small>
                            @else
                                <span class="badge bg-danger">No</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.blacklist.show', $record) }}" class="btn btn-sm btn-info">View</a>
                            @if(!$record->lifted_at)
                                <form action="{{ route('admin.blacklist.update', $record) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="btn btn-sm btn-success">Lift Blacklist</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $blacklistRecords->links() }}
    </div>
</div>
@endsection