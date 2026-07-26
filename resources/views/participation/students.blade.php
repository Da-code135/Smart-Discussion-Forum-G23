@extends('layouts.app')

@section('title', 'Participation Marks')
@section('activeNav', 'participation')

@section('content')
<div class="page-stack">
    <header class="page-header">
        <div class="page-header-row">
            <div>
                <h1>Participation marks</h1>
                <p>Cumulative engagement scores for your students — quizzes, topics, replies and daily activity.</p>
            </div>
        </div>
    </header>

    <section class="filter-section" aria-label="Point weights">
        <span class="meta-text">Point weights:</span>
        <span class="badge badge-secondary">Quiz completed &middot; {{ $weights['quiz_completed'] + 0 }} pts</span>
        <span class="badge badge-secondary">Topic created &middot; {{ $weights['topic_created'] + 0 }} pts</span>
        <span class="badge badge-secondary">Reply posted &middot; {{ $weights['reply_posted'] + 0 }} pts</span>
        <span class="badge badge-secondary">Daily login &middot; {{ $weights['daily_login'] + 0 }} pts</span>
    </section>

    @if ($students->isEmpty())
        <div class="empty-state">
            <span class="material-symbols-outlined">group_off</span>
            <h3>No students found</h3>
            <p>There are no students in your groups yet.</p>
        </div>
    @else
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Group</th>
                        <th>Quizzes</th>
                        <th>Topics</th>
                        <th>Replies</th>
                        <th>Active days</th>
                        <th>Total points</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $student)
                        @php $summary = $summaries[$student->id] ?? ['total' => 0, 'counts' => array_fill_keys($activityTypes, 0)]; @endphp
                        <tr>
                            <td>
                                <strong>{{ $student->full_name }}</strong>
                                <div class="meta-text">{{ $student->email }}</div>
                            </td>
                            <td>{{ $student->group?->group_name ?? '—' }}</td>
                            <td>{{ $summary['counts']['quiz_completed'] }}</td>
                            <td>{{ $summary['counts']['topic_created'] }}</td>
                            <td>{{ $summary['counts']['reply_posted'] }}</td>
                            <td>{{ $summary['counts']['daily_login'] }}</td>
                            <td><strong>{{ $summary['total'] + 0 }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
