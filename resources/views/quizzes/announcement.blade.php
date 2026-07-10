@extends('layouts.app')

@section('title', 'Quiz: ' . $quiz->title)

@section('content')
<div class="page-shell">
    <div class="page-shell__main page-stack" style="max-width: 640px; margin: 0 auto;">

        {{-- Quiz Title & Description --}}
        <div class="bento-card">
            <div class="text-center mb-6">
                <span class="material-symbols-outlined" style="font-size: 48px; color: #3b82f6; margin-bottom: 12px;">quiz</span>
                <h1>{{ $quiz->title }}</h1>
                @if ($quiz->description)
                    <p class="text-gray-600">{{ $quiz->description }}</p>
                @endif
            </div>

            {{-- Quiz Metadata Card --}}
            <div style="background: #f0f5ff; border: 1px solid #dbeafe; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; text-align: center;">
                    <div>
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">DATE & TIME</div>
                        <div style="font-weight: 600; font-size: 14px;">
                            {{ $quizStatus['scheduled_time']->format('M j, Y \a\t g:ia') }}
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">DURATION</div>
                        <div style="font-weight: 600; font-size: 14px;">{{ $quiz->duration_minutes }} minutes</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">QUESTIONS</div>
                        <div style="font-weight: 600; font-size: 14px;">{{ $quiz->questions()->count() }}</div>
                    </div>
                </div>
            </div>

            {{-- Action Area: Join button or countdown --}}
            @if ($quizStatus['has_started'])
                {{-- Quiz is live — show JOIN button --}}
                <div class="text-center mb-4">
                    <a href="{{ route('quizzes.attempt', $quiz->quiz_id) }}" class="btn btn-success btn-lg">
                        <span class="material-symbols-outlined">play_arrow</span>
                        Join Quiz Now
                    </a>
                </div>
                <p class="text-sm text-gray-500 text-center">Quiz is live. Click above to enter.</p>

            @elseif ($quizStatus['time_until_start_seconds'] > 0)
                {{-- Quiz hasn't started — show countdown or human-readable time --}}
                <div class="text-center mb-4">
                    <div style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">
                        @if ($quizStatus['time_until_start_seconds'] > 3600)
                            Quiz starts
                        @else
                            Quiz starts in
                        @endif
                    </div>

                    @if ($quizStatus['time_until_start_seconds'] > 3600)
                        {{-- More than 1 hour away: show human-readable time --}}
                        <div style="font-size: 20px; font-weight: 600; color: #2563eb;">
                            {{ $quizStatus['scheduled_time']->format('l, M j \a\t g:ia') }}
                        </div>
                        <div style="font-size: 14px; color: #6b7280; margin-top: 4px;">
                            @if ($quizStatus['time_until_start_seconds'] > 86400)
                                {{ floor($quizStatus['time_until_start_seconds'] / 86400) }} day(s) away
                            @else
                                {{ ceil($quizStatus['time_until_start_seconds'] / 3600) }} hour(s) away
                            @endif
                        </div>
                    @else
                        {{-- Within 1 hour: show ticking countdown --}}
                        <div style="font-size: 40px; font-weight: 700; color: #2563eb; font-variant-numeric: tabular-nums;" id="countdown">
                            {{ $quizStatus['time_until_start_display'] }}
                        </div>
                    @endif
                </div>

                @if ($quizStatus['time_until_start_seconds'] <= 3600)
                    <script>
                        let secondsRemaining = {{ $quizStatus['time_until_start_seconds'] }};

                        function updateCountdown() {
                            if (secondsRemaining <= 0) {
                                location.reload();
                                return;
                            }

                            const hours = Math.floor(secondsRemaining / 3600);
                            const minutes = Math.floor((secondsRemaining % 3600) / 60);
                            const secs = secondsRemaining % 60;

                            document.getElementById('countdown').textContent =
                                String(hours).padStart(2, '0') + ':' +
                                String(minutes).padStart(2, '0') + ':' +
                                String(secs).padStart(2, '0');

                            secondsRemaining--;
                        }

                        setInterval(updateCountdown, 1000);
                        updateCountdown();
                    </script>
                @endif
            @else
                {{-- Quiz time has passed --}}
                <p class="text-center" style="color: #dc2626; font-weight: 600;">Quiz time has passed.</p>
            @endif
        </div>

        {{-- Instructions Card --}}
        <div class="bento-card">
            <h3 style="margin-bottom: 16px;">Quiz Instructions</h3>
            <ul style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 10px;">
                <li style="display: flex; align-items: flex-start; gap: 8px;">
                    <span style="color: #16a34a;">&#10003;</span>
                    <span>You have <strong>{{ $quiz->duration_minutes }} minutes</strong> to complete this quiz.</span>
                </li>
                <li style="display: flex; align-items: flex-start; gap: 8px;">
                    <span style="color: #16a34a;">&#10003;</span>
                    <span>Answer all questions within the time limit.</span>
                </li>
                <li style="display: flex; align-items: flex-start; gap: 8px;">
                    <span style="color: #16a34a;">&#10003;</span>
                    <span>The quiz will <strong>auto-submit</strong> when time expires.</span>
                </li>
                @if ($quiz->configuration && $quiz->configuration->lock_screen_on_start)
                    <li style="display: flex; align-items: flex-start; gap: 8px;">
                        <span style="color: #dc2626;">&#9888;</span>
                        <span>The quiz interface is <strong>locked</strong> — you cannot navigate away or minimize the window.</span>
                    </li>
                @endif
                @if ($quiz->configuration && $quiz->configuration->show_results_after_close)
                    <li style="display: flex; align-items: flex-start; gap: 8px;">
                        <span style="color: #16a34a;">&#10003;</span>
                        <span>Your results will be available immediately after submission.</span>
                    </li>
                @endif
            </ul>
        </div>

    </div>
</div>
@endsection
