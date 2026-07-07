@extends('layouts.app')

@section('title', 'Performance Report: ' . $quiz->title)

@section('content')
<div class="page-stack">
    <div class="page-header">
        <div class="page-header-row">
            <div>
                <h1>Performance Report: {{ $quiz->title }}</h1>
                <p>Class performance summary and student breakdown.</p>
            </div>
            <a href="{{ route('quizzes.edit', $quiz->quiz_id) }}" class="btn btn-secondary">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Quiz
            </a>
        </div>
    </div>

    @if (!$stats)
        <div class="empty-state">
            <span class="material-symbols-outlined" style="font-size: 40px;">bar_chart</span>
            <h2>No data yet</h2>
            <p>No students have completed this quiz yet.</p>
        </div>
    @else
        {{-- Stats Summary Cards --}}
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem;">
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; font-weight: 700; color: var(--app-accent);">{{ $stats['total_attempts'] }}</div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">Total Attempts</div>
            </div>
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; font-weight: 700; color: #16a34a;">{{ number_format($stats['average_score'], 1) }}</div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">Average Score</div>
            </div>
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; font-weight: 700; color: #2563eb;">{{ number_format($stats['highest_score'], 1) }}</div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">Highest Score</div>
            </div>
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; font-weight: 700; color: #dc2626;">{{ number_format($stats['lowest_score'], 1) }}</div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">Lowest Score</div>
            </div>
        </div>

        {{-- Student Results Table --}}
        <div class="card">
            <div class="card-header">
                <h3 style="margin: 0;">Student Results</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th style="text-align: center;">Score</th>
                                <th style="text-align: center;">Percentage</th>
                                <th style="text-align: center;">Participation</th>
                                <th style="text-align: center;">Final Grade</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($grades as $grade)
                                <tr>
                                    <td>
                                        <strong>{{ $grade->student->full_name ?? 'Deleted User' }}</strong>
                                    </td>
                                    <td style="text-align: center;">
                                        {{ number_format($grade->total_score, 1) }}/{{ number_format($grade->max_score, 1) }}
                                    </td>
                                    <td style="text-align: center; font-weight: 600;">
                                        {{ number_format($grade->percentage, 1) }}%
                                    </td>
                                    <td style="text-align: center;">
                                        +{{ number_format($grade->participation_mark, 1) }}
                                    </td>
                                    <td style="text-align: center; font-weight: 700;">
                                        {{ number_format($grade->final_grade, 1) }}
                                    </td>
                                    <td style="text-align: center;">
                                        @if ($grade->percentage >= 80)
                                            <span class="badge badge-success">Pass</span>
                                        @elseif ($grade->percentage >= 50)
                                            <span class="badge badge-warning">Needs Review</span>
                                        @else
                                            <span class="badge badge-danger">Low Score</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                        No students have submitted this quiz yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
