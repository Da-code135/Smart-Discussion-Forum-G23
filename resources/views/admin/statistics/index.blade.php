{{-- Load Chart.js first so all chart components that follow can use it --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
@endpush

@push('styles')
<style>
    .stats-group-card {
        margin-bottom: 2rem;
        border: 1px solid var(--app-border);
        border-radius: var(--radius-card);
        background: var(--app-card-bg);
        box-shadow: var(--shadow-card);
        overflow: hidden;
    }

    .stats-group-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid var(--app-border);
    }

    .stats-group-header h2 {
        font-size: 16px;
        font-weight: 600;
        color: var(--app-text-primary);
    }

    .stats-group-header .last-updated {
        font-size: 12px;
        color: var(--app-text-muted);
        margin-top: 2px;
    }

    .stats-body {
        padding: 24px;
    }

    /* Quick metric chips */
    .metric-chips {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 28px;
    }

    .metric-chip {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 18px;
        border-radius: var(--radius-card);
        background: color-mix(in srgb, var(--app-page-bg) 60%, var(--app-card-bg));
        border: 1px solid var(--app-border);
        text-align: center;
    }

    .metric-chip__value {
        font: 700 28px/1.1 var(--font-headline);
        color: var(--app-text-primary);
        margin-bottom: 4px;
    }

    .metric-chip__label {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--app-text-muted);
    }

    .metric-chip--accent .metric-chip__value { color: var(--app-accent); }
    .metric-chip--secondary .metric-chip__value { color: var(--app-secondary); }
    .metric-chip--success .metric-chip__value { color: #2e7d32; }
    .metric-chip--warning .metric-chip__value { color: #e65100; }
    .metric-chip--danger .metric-chip__value { color: var(--app-danger); }
    .metric-chip--info .metric-chip__value { color: #1565c0; }
    .metric-chip--neutral .metric-chip__value { color: #6a1b9a; }

    /* Charts grid */
    .charts-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }

    .charts-row--single {
        grid-template-columns: 1fr;
    }

    .chart-box {
        background: color-mix(in srgb, var(--app-page-bg) 40%, var(--app-card-bg));
        border: 1px solid var(--app-border);
        border-radius: var(--radius-card);
        padding: 20px;
    }

    .chart-box h3 {
        font-size: 13px;
        font-weight: 600;
        color: var(--app-text-muted);
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 14px;
    }

    .chart-box__chart {
        position: relative;
        width: 100%;
    }

    /* Comparison overview */
    .comparison-overview {
        margin-bottom: 2rem;
    }

    .comparison-table-wrap {
        overflow-x: auto;
        border: 1px solid var(--app-border);
        border-radius: var(--radius-card);
        background: var(--app-card-bg);
        box-shadow: var(--shadow-card);
    }

    .comparison-table-wrap table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .comparison-table-wrap th {
        padding: 14px 16px;
        border-bottom: 1px solid var(--app-border-strong);
        color: var(--app-text-muted);
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        text-align: left;
        white-space: nowrap;
    }

    .comparison-table-wrap td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--app-border);
        color: var(--app-text-secondary);
        white-space: nowrap;
    }

    .comparison-table-wrap tr:last-child td {
        border-bottom: 0;
    }

    .comparison-table-wrap tbody tr:hover {
        background: color-mix(in srgb, var(--app-accent-soft) 30%, var(--app-card-bg));
    }

    .bar-mini {
        display: inline-block;
        height: 8px;
        border-radius: 999px;
        min-width: 4px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .charts-row {
            grid-template-columns: 1fr;
        }

        .metric-chips {
            grid-template-columns: repeat(2, 1fr);
        }

        .stats-group-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
    }

    /* Active percentage ring */
    .ring-progress {
        position: relative;
        width: 80px;
        height: 80px;
        margin: 0 auto 8px;
    }
    .ring-progress svg {
        transform: rotate(-90deg);
    }
    .ring-progress__bg {
        fill: none;
        stroke: var(--app-border);
        stroke-width: 6;
    }
    .ring-progress__fill {
        fill: none;
        stroke: var(--app-accent);
        stroke-width: 6;
        stroke-linecap: round;
        transition: stroke-dashoffset 0.8s ease;
    }
    .ring-progress__text {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font: 700 18px/1 var(--font-headline);
        color: var(--app-text-primary);
    }
    .ring-progress__text small {
        font-size: 11px;
        font-weight: 400;
    }
</style>
@endpush

@extends('layouts.app')

@section('title', 'Statistics Dashboard')

@section('content')
<div class="page-stack">
    <header class="page-header">
        <h1>Platform Statistics</h1>
        <p>Group engagement metrics visualized &mdash; click <strong>Recalculate</strong> on any group to pull live data.</p>
    </header>

    {{-- ================================================================
         COMPARISON OVERVIEW — shown when multiple groups exist
         ================================================================ --}}
    @if (count($groupStats) > 1)
    <section class="comparison-overview">
        <div class="page-header" style="margin-bottom: 1rem;">
            <h2>Groups at a Glance</h2>
            <p>Side-by-side comparison across all groups you administer.</p>
        </div>

        <div class="comparison-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Members</th>
                        <th>Active</th>
                        <th>Engagement</th>
                        <th>Topics</th>
                        <th>Posts</th>
                        <th>Unanswered</th>
                        <th>Inactive</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $maxMembers = max(array_map(fn($i) => $i['stats']->total_members, $groupStats)) ?: 1;
                    @endphp
                    @foreach ($groupStats as $item)
                        @php
                            $s = $item['stats'];
                            $pct = $s->activePercentage();
                            $barColor = $pct >= 50 ? 'var(--app-success)' : ($pct >= 25 ? 'var(--app-secondary)' : 'var(--app-danger)');
                        @endphp
                        <tr>
                            <td><strong>{{ $item['group']->group_name }}</strong></td>
                            <td>{{ $s->total_members }}</td>
                            <td>{{ $s->active_members_this_week }}</td>
                            <td>
                                <span class="bar-mini" style="width: {{ max(4, ($s->total_members / $maxMembers) * 60) }}px; background: {{ $barColor }};"></span>
                                <span style="margin-left: 6px; font-size: 12px; font-weight: 600;">{{ $pct }}%</span>
                            </td>
                            <td>{{ $s->total_topics }}</td>
                            <td>{{ $s->total_posts }}</td>
                            <td style="color: {{ $s->unanswered_questions > 0 ? 'var(--app-danger)' : 'inherit' }};">
                                {{ $s->unanswered_questions }}
                            </td>
                            <td>{{ $s->inactive_members_30days }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Comparison bar chart --}}
        <div class="chart-box" style="margin-top: 1rem;">
            <h3>All Groups — Members Overview</h3>
            <div class="chart-box__chart" style="height: 220px;">
                <x-chart
                    type="bar"
                    :labels="array_map(fn($i) => $i['group']->group_name, $groupStats)"
                    :datasets="[
                        ['label' => 'Total Members', 'data' => array_map(fn($i) => $i['stats']->total_members, $groupStats)],
                        ['label' => 'Active This Week', 'data' => array_map(fn($i) => $i['stats']->active_members_this_week, $groupStats)],
                    ]"
                    height="220"
                />
            </div>
        </div>
    </section>
    @endif

    {{-- ================================================================
         PER-GROUP DETAIL
         ================================================================ --}}
    @forelse ($groupStats as $item)
        @php
            $group = $item['group'];
            $stats = $item['stats'];
            $lastUpdated = $stats->last_calculated_at
                ? $stats->last_calculated_at->diffForHumans()
                : 'Not yet calculated';
            $pct = $stats->activePercentage();
            $circumference = 2 * 22 / 7 * 34; // 2 * pi * r (r=34)
        @endphp

        <div class="stats-group-card">
            {{-- Group Header --}}
            <div class="stats-group-header">
                <div>
                    <h2>{{ $group->group_name }}</h2>
                    <div class="last-updated">Last updated: {{ $lastUpdated }}</div>
                </div>
                <form method="POST" action="{{ route('admin.statistics.recalculate', $group->id) }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <span class="material-symbols-outlined" style="font-size: 1rem; vertical-align: middle;">refresh</span>
                        Recalculate
                    </button>
                </form>
            </div>

            <div class="stats-body">
                {{-- Quick metric chips --}}
                <div class="metric-chips">
                    <div class="metric-chip metric-chip--accent">
                        <div class="metric-chip__value">{{ $stats->total_members }}</div>
                        <div class="metric-chip__label">Total Members</div>
                    </div>
                    <div class="metric-chip" style="background: transparent; box-shadow: none; border: none;">
                        <div class="ring-progress">
                            <svg width="80" height="80" viewBox="0 0 80 80">
                                <circle class="ring-progress__bg" cx="40" cy="40" r="34"/>
                                <circle class="ring-progress__fill" cx="40" cy="40" r="34"
                                    stroke-dasharray="{{ $circumference }}"
                                    stroke-dashoffset="{{ $circumference - ($pct / 100) * $circumference }}"
                                    stroke="{{ $pct >= 50 ? 'var(--app-success)' : ($pct >= 25 ? 'var(--app-secondary)' : 'var(--app-danger)') }}"/>
                            </svg>
                            <div class="ring-progress__text">{{ $pct }}<small>%</small></div>
                        </div>
                        <div class="metric-chip__label">Active This Week</div>
                    </div>
                    <div class="metric-chip metric-chip--success">
                        <div class="metric-chip__value">{{ $stats->total_topics }}</div>
                        <div class="metric-chip__label">Total Topics</div>
                    </div>
                    <div class="metric-chip metric-chip--info">
                        <div class="metric-chip__value">{{ $stats->total_posts }}</div>
                        <div class="metric-chip__label">Total Posts</div>
                    </div>
                    <div class="metric-chip metric-chip--warning">
                        <div class="metric-chip__value">{{ $stats->unanswered_questions }}</div>
                        <div class="metric-chip__label">Unanswered</div>
                    </div>
                    <div class="metric-chip metric-chip--neutral">
                        <div class="metric-chip__value">{{ $stats->inactive_members_30days }}</div>
                        <div class="metric-chip__label">Inactive 30+ Days</div>
                    </div>
                </div>

                {{-- Charts row 1: Membership breakdown --}}
                <div class="charts-row">
                    <div class="chart-box">
                        <h3>Membership Breakdown</h3>
                        <div class="chart-box__chart" style="height: 250px;">
                            <x-chart
                                type="bar"
                                :labels="['Total', 'Active This Week', 'Inactive 30+ Days']"
                                :datasets="[[
                                    'label' => 'Members',
                                    'data' => [$stats->total_members, $stats->active_members_this_week, $stats->inactive_members_30days],
                                    'backgroundColor' => ['#59623e', '#4caf50', '#f44336'],
                                ]]"
                                height="250"
                            />
                        </div>
                    </div>
                    <div class="chart-box">
                        <h3>Engagement Distribution</h3>
                        <div class="chart-box__chart" style="height: 250px;">
                            <x-chart
                                type="doughnut"
                                :labels="['Active This Week', 'Inactive 30+ Days', 'Other Members']"
                                :datasets="[[
                                    'label' => 'Engagement',
                                    'data' => [
                                        $stats->active_members_this_week,
                                        $stats->inactive_members_30days,
                                        max(0, $stats->total_members - $stats->active_members_this_week - $stats->inactive_members_30days),
                                    ],
                                ]]"
                                height="250"
                            />
                        </div>
                    </div>
                </div>

                {{-- Charts row 2: Content metrics --}}
                <div class="charts-row">
                    <div class="chart-box">
                        <h3>Content Activity</h3>
                        <div class="chart-box__chart" style="height: 200px;">
                            <x-chart
                                type="bar"
                                :labels="['Topics', 'Posts', 'Avg Posts/Topic']"
                                :datasets="[[
                                    'label' => 'Count',
                                    'data' => [$stats->total_topics, $stats->total_posts, $stats->averagePostsPerTopic()],
                                    'backgroundColor' => ['#7c5639', '#2196f3', '#ff9800'],
                                ]]"
                                height="200"
                            />
                        </div>
                    </div>
                    <div class="chart-box">
                        <h3>Questions Status</h3>
                        <div class="chart-box__chart" style="height: 200px;">
                            @php
                                $totalQuestions = $stats->unanswered_questions;
                            @endphp
                            <x-chart
                                type="doughnut"
                                :labels="['Answered', 'Unanswered']"
                                :datasets="[[
                                    'label' => 'Questions',
                                    'data' => [
                                        max(0, $stats->total_topics - $stats->unanswered_questions),
                                        $stats->unanswered_questions,
                                    ],
                                    'backgroundColor' => ['#4caf50', '#f44336'],
                                ]]"
                                height="200"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
