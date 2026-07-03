@extends('layouts.app')

@section('title', "Stats - $group->group_name")
@section('admin')

@section('content')
<div class="container">
    <div class="admin-header">
        <h1>{{ $group->group_name }} — Statistics</h1>
        <a href="{{ route('admin.group-statistics.index') }}" class="btn btn-secondary">
            &larr; Back to all groups
        </a>
    </div>

    {{-- Row 1: Membership --}}
    <div class="dashboard-grid" style="margin-bottom: 2rem;">
        <div class="dashboard-card">
            <h3>Total Members</h3>
            <div class="number">{{ $total_members }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Active</h3>
            <div class="number">{{ $active_members }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Warned</h3>
            <div class="number">{{ $warned_members }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Blacklisted</h3>
            <div class="number">{{ $blacklisted_members }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Inactive</h3>
            <div class="number">{{ $inactive_members }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Never Posted</h3>
            <div class="number">{{ $lurkers }}</div>
        </div>
    </div>

    {{-- Row 2: Topics & Posts --}}
    <div class="dashboard-grid" style="margin-bottom: 2rem;">
        <div class="dashboard-card">
            <h3>Total Topics</h3>
            <div class="number">{{ $total_topics }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Discussions</h3>
            <div class="number">{{ $discussion_topics }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Questions</h3>
            <div class="number">{{ $question_topics }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Unanswered Questions</h3>
            <div class="number" style="color: {{ $unanswered_questions > 0 ? '#dc3545' : '#28a745' }};">
                {{ $unanswered_questions }}
            </div>
        </div>
        <div class="dashboard-card">
            <h3>Total Posts</h3>
            <div class="number">{{ $total_posts }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Avg Posts/Topic</h3>
            <div class="number">{{ $avg_posts_per_topic }}</div>
        </div>
    </div>

    {{-- Row 3: Moderation --}}
    <div class="dashboard-grid" style="margin-bottom: 2rem;">
        <div class="dashboard-card">
            <h3>Removed Posts</h3>
            <div class="number">{{ $removed_posts }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Reported Posts</h3>
            <div class="number">{{ $reported_posts }}</div>
        </div>
        <div class="dashboard-card">
            <h3>Avg Posts/Member</h3>
            <div class="number">{{ $avg_posts_per_member }}</div>
        </div>
    </div>

    {{-- Weekly Topic Trend --}}
    <div class="admin-header">
        <h2>Topics Created Per Week (Last 12 Weeks)</h2>
    </div>
    <div class="table-container" style="margin-bottom: 2rem;">
        <table>
            <thead>
                <tr>
                    <th>Week</th>
                    <th>Topics Created</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($weekly_topics as $week)
                    <tr>
                        <td>{{ $week['week'] }}</td>
                        <td>{{ $week['topics'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" style="text-align: center;">No topics in the last 12 weeks</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Most Active Members --}}
    <div class="admin-header">
        <h2>Top 10 Most Active Members</h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Posts</th>
                    <th>Last Active</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($top_members as $index => $member)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $member['full_name'] }}</td>
                        <td>{{ $member['post_count'] }}</td>
                        <td>{{ $member['last_active'] ?? 'Never' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center;">No posts yet</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection