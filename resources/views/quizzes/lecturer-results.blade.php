@extends('layouts.app')

@section('title', 'Quiz Results Overview')
@section('activeNav', 'quiz-results')

@section('content')
<div class="page-stack">
    <div class="page-header">
        <div class="page-header-row">
            <div>
                <h1>{{ Auth::user()->isSystemAdmin() ? 'All Quiz Results' : 'My Quiz Results' }}</h1>
                <p>View marks and performance for all target members who took your quizzes.</p>
            </div>
        </div>
    </div>

    @if ($quizzes->isEmpty())
        <div class="empty-state">
            <span class="material-symbols-outlined" style="font-size: 40px;">bar_chart</span>
            <h2>No quiz results yet</h2>
            <p>There are no quizzes with completed results to display.</p>
        </div>
    @else
        @foreach ($quizzes as $quiz)
            <div class="card" style="margin-bottom: 1.5rem;">
                {{-- Quiz Header --}}
                <div class="card-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div>
                            <h2 style="margin: 0;">{{ $quiz->title }}</h2>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem; color: var(--text-muted);">
                                Group: <strong>{{ $quiz->group->group_name ?? 'General' }}</strong>
                                &middot;
                                Target: <strong>{{ $quiz->target_category }}</strong>
                                &middot;
                                Questions: <strong>{{ $quiz->questions_count }}</strong>
                                &middot;
                                {{ $quiz->scheduled_date?->format('M d, Y') ?? 'No date' }}
                                @if (Auth::user()->isSystemAdmin())
                                    &middot;
                                    Lecturer: <strong>{{ $quiz->lecturer->full_name ?? 'Unknown' }}</strong>
                                @endif
                            </p>
                        </div>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <span class="badge {{ $quiz->published_at ? 'badge-success' : 'badge-warning' }}">
                                {{ $quiz->published_at ? 'Published' : 'Draft' }}
                            </span>
                            <a href="{{ route('quizzes.report', $quiz->quiz_id) }}" class="btn btn-secondary btn-sm">
                                <span class="material-symbols-outlined">detailed_chart</span>
                                Full Report
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Stats Summary --}}
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; padding: 1rem; background: var(--bg-muted, #f8f9fa); border-bottom: 1px solid var(--border-color);">
                    <div style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--app-accent);">{{ $quiz->stats['total_attempts'] }}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Attempts</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--app-accent);">{{ $quiz->stats['average_score'] }}%</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Average</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: 700; color: #16a34a;">{{ $quiz->stats['highest_score'] }}%</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Highest</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: 700; color: #dc2626;">{{ $quiz->stats['lowest_score'] }}%</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Lowest</div>
                    </div>
                </div>

                {{-- Student Results Table --}}
                @if ($quiz->grades->isNotEmpty())
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--bg-muted, #f8f9fa);">
                                    <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Student</th>
                                    <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Email</th>
                                    <th style="text-align: center; padding: 0.75rem 1rem; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Score</th>
                                    <th style="text-align: center; padding: 0.75rem 1rem; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Percentage</th>
                                    <th style="text-align: center; padding: 0.75rem 1rem; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Participation</th>
                                    <th style="text-align: center; padding: 0.75rem 1rem; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Final Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quiz->grades as $grade)
                                    <tr style="border-top: 1px solid var(--border-color);">
                                        <td style="padding: 0.75rem 1rem; font-weight: 600;">{{ $grade->student->full_name ?? 'Deleted User' }}</td>
                                        <td style="padding: 0.75rem 1rem; color: var(--text-muted);">{{ $grade->student->email ?? '—' }}</td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">{{ number_format($grade->total_score, 1) }}/{{ number_format($grade->max_score, 1) }}</td>
                                        <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 600;">{{ number_format($grade->percentage, 1) }}%</td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">+{{ number_format($grade->participation_mark, 1) }}</td>
                                        <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 700;">
                                            {{ number_format($grade->final_grade, 1) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                        No target members have taken this quiz yet.
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>
@endsection
