@extends('layouts.app')

@section('title', 'Group Statistics')
@section('admin')

@section('content')
<div class="container">
    <div class="admin-header">
        <h1>Group Statistics</h1>
        <p>Overview of all groups — click a group for detailed analytics</p>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Group Name</th>
                    <th>Members</th>
                    <th>Active Members</th>
                    <th>Topics</th>
                    <th>Posts</th>
                    <th>Last Activity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr>
                        <td><strong>{{ $group['group_name'] }}</strong></td>
                        <td><span class="member-badge">{{ $group['total_members'] }}</span></td>
                        <td>{{ $group['active_members'] }}</td>
                        <td>{{ $group['total_topics'] }}</td>
                        <td>{{ $group['total_posts'] }}</td>
                        <td>
                            {{ $group['last_activity'] ? \Carbon\Carbon::parse($group['last_activity'])->diffForHumans() : 'No activity' }}
                        </td>
                        <td>
                            <a href="{{ route('admin.group-statistics.show', $group['id']) }}"
                               class="btn btn-primary btn-sm">
                                View Stats
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem;">
                            No groups found
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
