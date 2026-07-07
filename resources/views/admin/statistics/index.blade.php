@extends('layouts.app')

@section('title', 'Statistics Dashboard')

@section('content')
<div class="page-stack">
    <header class="page-header">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h1>Platform Statistics</h1>
                <p>Engagement metrics for all groups you administer &mdash; refresh a group to pull live data.</p>
            </div>
        </div>
    </header>

    @forelse ($groupStats as $item)
        @php
            $group = $item['group'];
            $stats = $item['stats'];
            $lastUpdated = $stats->last_calculated_at
                ? $stats->last_calculated_at->diffForHumans()
                : 'Not yet calculated';
        @endphp

        <section class="card" style="margin-bottom: 1.5rem;">
            {{-- Group header row --}}
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h2 style="margin: 0;">{{ $group->group_name }}</h2>
                    <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--text-muted);">
                        Last updated: {{ $lastUpdated }}
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.statistics.recalculate', $group->id) }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <span class="material-symbols-outlined" style="font-size: 1rem; vertical-align: middle;">refresh</span>
                        Recalculate
                    </button>
                </form>
            </div>

            {{-- Metrics grid --}}
            <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">

                {{-- Total Members --}}
                <div class="dashboard-card">
                    <h3>Total Members</h3>
                    <div class="number">{{ $stats->total_members }}</div>
                </div>

                {{-- Active This Week --}}
                <div class="dashboard-card">
                    <h3>Active This Week</h3>
                    <div class="number" style="color: #4caf50;">
                        {{ $stats->active_members_this_week }}
                        <span style="font-size: 0.875rem; font-weight: 400; color: var(--text-muted);">
                            ({{ $stats->activePercentage() }}%)
                        </span>
                    </div>
                </div>

                {{-- Total Topics --}}
                <div class="dashboard-card">
                    <h3>Total Topics</h3>
                    <div class="number" style="color: #2196f3;">{{ $stats->total_topics }}</div>
                </div>

                {{-- Total Posts --}}
                <div class="dashboard-card">
                    <h3>Total Posts</h3>
                    <div class="number" style="color: #ff9800;">
                        {{ $stats->total_posts }}
                        <span style="font-size: 0.875rem; font-weight: 400; color: var(--text-muted);">
                            ({{ $stats->averagePostsPerTopic() }} avg/topic)
                        </span>
                    </div>
                </div>

                {{-- Unanswered Questions --}}
                <div class="dashboard-card">
                    <h3>Unanswered Questions</h3>
                    <div class="number" style="color: #f44336;">{{ $stats->unanswered_questions }}</div>
                </div>

                {{-- Inactive 30+ Days --}}
                <div class="dashboard-card">
                    <h3>Inactive 30+ Days</h3>
                    <div class="number" style="color: #9c27b0;">{{ $stats->inactive_members_30days }}</div>
                </div>

            </div>
        </section>
    @empty
        <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
            <span class="material-symbols-outlined" style="font-size: 3rem; color: var(--text-muted);">bar_chart</span>
            <h2>No groups available</h2>
            <p style="color: var(--text-muted);">
                You don't administer any groups yet, or there are no groups with statistics to display.
            </p>
        </div>
    @endforelse
</div>
@endsection
