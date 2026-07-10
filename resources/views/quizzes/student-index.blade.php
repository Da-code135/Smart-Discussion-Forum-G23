@extends('layouts.app')

@section('title', 'My Quizzes')
@section('activeNav', 'quizzes')

@section('content')
<div class="page-shell">
    <div class="page-shell__main page-stack">
        <header class="page-header">
            <h1>My Quizzes</h1>
            <p>Upcoming, live, and completed quizzes for your group.</p>
        </header>

        @if ($quizzes->isEmpty())
            <div class="empty-state">
                <span class="material-symbols-outlined" style="font-size: 40px;">quiz</span>
                <p>No quizzes available for you right now.</p>
            </div>
        @else
            @foreach ($quizzes as $item)
                @php
                    $quiz = $item->quiz;
                    $badge = '';
                    $badgeClass = '';
                    $actionLabel = '';
                    $actionRoute = '';

                    $quizEnded = $item->scheduled->copy()->addMinutes($quiz->duration_minutes)->isPast();

                    if ($item->is_submitted) {
                        $badge = 'Completed';
                        $badgeClass = 'badge-success';
                        $actionLabel = 'View result';
                        $actionRoute = route('quizzes.result', $quiz);
                    } elseif ($item->attempt && !$item->is_submitted) {
                        $badge = 'In progress';
                        $badgeClass = 'badge-warning';
                        $actionLabel = 'Resume quiz';
                        $actionRoute = route('quizzes.attempt', $quiz);
                    } elseif ($item->is_live && !$quizEnded) {
                        $badge = 'Live now';
                        $badgeClass = 'badge-danger';
                        $actionLabel = 'Join quiz';
                        $actionRoute = route('quizzes.attempt', $quiz);
                    } elseif ($quizEnded && !$item->attempt) {
                        $badge = 'Missed';
                        $badgeClass = 'badge-secondary';
                        $actionLabel = 'View';
                        $actionRoute = route('quizzes.announcement', $quiz);
                    } else {
                        $badge = 'Upcoming';
                        $badgeClass = 'badge-secondary';
                        $actionLabel = 'View details';
                        $actionRoute = route('quizzes.announcement', $quiz);
                    }
                @endphp

                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2>{{ $quiz->title }}</h2>
                            <p>{{ $quiz->description }}</p>
                        </div>
                        <span class="badge {{ $badgeClass }}">{{ $badge }}</span>
                    </div>
                    <div class="card-body">
                        <div class="profile-summary-list">
                            <span><strong>Lecturer:</strong> {{ $quiz->lecturer->full_name ?? 'Unknown' }}</span>
                            <span><strong>Duration:</strong> {{ $quiz->duration_minutes }} minutes</span>
                            <span><strong>Questions:</strong> {{ $quiz->questions_count }}</span>
                            <span><strong>Scheduled:</strong> {{ $item->scheduled->format('M d, Y \a\t H:i') }}</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="{{ $actionRoute }}" class="btn btn-primary">{{ $actionLabel }}</a>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection
