@extends('layouts.app')

@section('title', 'Report: ' . $quiz->title)

@section('content')
<div class="page-shell">
    <div class="page-shell__main page-stack" style="max-width: 800px; margin: 0 auto;">

        {{-- Header --}}
        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
            <a href="{{ route('quizzes.index') }}" class="btn btn-tertiary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span>
                Back
            </a>
            <div>
                <h1 style="margin: 0; font-size: 22px;">Performance Report</h1>
                <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;">{{ $quiz->title }}</p>
            </div>
        </div>

        {{-- Statistics Summary Cards --}}
        @if ($stats)
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px;">
                <div style="padding: 20px; background: #f3f4f6; border-radius: 8px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700;">{{ $stats['total_attempts'] }}</div>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #6b7280;">Total Attempts</p>
                </div>
                <div style="padding: 20px; background: #eff6ff; border-radius: 8px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #2563eb;">{{ number_format($stats['average_score'], 1) }}</div>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #6b7280;">Average Score</p>
                </div>
                <div style="padding: 20px; background: #f0fdf4; border-radius: 8px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #16a34a;">{{ number_format($stats['highest_score'], 1) }}</div>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #6b7280;">Highest Score</p>
                </div>
                <div style="padding: 20px; background: #fef2f2; border-radius: 8px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #dc2626;">{{ number_format($stats['lowest_score'], 1) }}</div>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #6b7280;">Lowest Score</p>
                </div>
            </div>
        @else
            <div style="background: #fef9c3; border: 1px solid #fcd34d; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 24px;">
                <span class="material-symbols-outlined" style="font-size: 32px; color: #ca8a04;">info</span>
                <p style="margin: 8px 0 0 0; font-weight: 600;">No attempts yet</p>
                <p style="margin: 4px 0 0 0; font-size: 14px; color: #6b7280;">Grades will appear here once students submit the quiz.</p>
            </div>
        @endif

        {{-- Student Results Table --}}
        <div class="bento-card">
            <h3 style="margin-bottom: 16px;">Student Breakdown</h3>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr style="background: #f3f4f6; border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 10px 12px; text-align: left; font-weight: 600;">Student</th>
                            <th style="padding: 10px 12px; text-align: center; font-weight: 600;">Score</th>
                            <th style="padding: 10px 12px; text-align: center; font-weight: 600;">%</th>
                            <th style="padding: 10px 12px; text-align: center; font-weight: 600;">Participation</th>
                            <th style="padding: 10px 12px; text-align: center; font-weight: 600;">Final Grade</th>
                            <th style="padding: 10px 12px; text-align: center; font-weight: 600;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grades as $grade)
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 10px 12px;">
                                    <span style="font-weight: 500;">{{ $grade->student->full_name }}</span>
                                    <span style="font-size: 12px; color: #9ca3af; display: block;">{{ $grade->student->email }}</span>
                                </td>
                                <td style="padding: 10px 12px; text-align: center;">
                                    {{ number_format($grade->total_score, 1) }}/{{ number_format($grade->max_score, 1) }}
                                </td>
                                <td style="padding: 10px 12px; text-align: center; font-weight: 600;">
                                    {{ number_format($grade->percentage, 1) }}%
                                </td>
                                <td style="padding: 10px 12px; text-align: center;">
                                    <span style="color: #059669;">+{{ number_format($grade->participation_mark, 1) }}</span>
                                </td>
                                <td style="padding: 10px 12px; text-align: center; font-weight: 700;">
                                    {{ number_format($grade->final_grade, 1) }}
                                </td>
                                <td style="padding: 10px 12px; text-align: center;">
                                    @if ($grade->percentage >= 80)
                                        <span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Pass</span>
                                    @elseif ($grade->percentage >= 50)
                                        <span style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Marginal</span>
                                    @else
                                        <span style="background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Needs Review</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="padding: 30px; text-align: center; color: #6b7280;">
                                    No grades recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Actions --}}
        <div style="display: flex; gap: 12px; justify-content: center; margin-top: 24px;">
            <a href="{{ route('quizzes.edit', $quiz->quiz_id) }}" class="btn btn-primary">
                <span class="material-symbols-outlined">edit</span>
                Edit Quiz
            </a>
            <a href="{{ route('quizzes.index') }}" class="btn btn-secondary">
                <span class="material-symbols-outlined">list</span>
                All Quizzes
            </a>
        </div>

    </div>
</div>
@endsection
