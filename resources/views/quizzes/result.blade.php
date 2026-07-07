@extends('layouts.app')

@section('title', 'Quiz Result: ' . $quiz->title)

@section('content')
<div class="page-shell">
    <div class="page-shell__main page-stack" style="max-width: 640px; margin: 0 auto;">

        <div class="bento-card">
            <div class="text-center mb-6">
                <span class="material-symbols-outlined" style="font-size: 48px; color: #16a34a; margin-bottom: 12px;">check_circle</span>
                <h1>{{ $quiz->title }} — Results</h1>
                @if ($attempt->is_auto_submit)
                    <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 6px; padding: 10px 16px; margin-top: 12px; display: inline-block; font-size: 14px;">
                        Auto-submitted — time expired.
                    </div>
                @endif
            </div>

            @if ($grade)
                {{-- Score Summary --}}
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 24px; margin-bottom: 24px;">
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; text-align: center;">
                        <div>
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">YOUR SCORE</div>
                            <div style="font-size: 28px; font-weight: 700; color: #16a34a;">
                                {{ number_format($grade->total_score, 1) }}
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">OUT OF</div>
                            <div style="font-size: 28px; font-weight: 700;">{{ number_format($grade->max_score, 1) }}</div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">PERCENTAGE</div>
                            <div style="font-size: 28px; font-weight: 700; color: #2563eb;">
                                {{ number_format($grade->percentage, 1) }}%
                            </div>
                        </div>
                    </div>
                </div>
            @else
                {{-- No grade yet — Person 4 is building grading --}}
                <div style="background: #fef9c3; border: 1px solid #fcd34d; border-radius: 8px; padding: 24px; text-align: center; margin-bottom: 24px;">
                    <span class="material-symbols-outlined" style="font-size: 36px; color: #ca8a04;">hourglass</span>
                    <h3 style="margin: 8px 0;">Grading in progress</h3>
                    <p style="color: #6b7280; font-size: 14px;">
                        Your quiz has been submitted. Your grade will appear here once grading is complete.
                    </p>
                </div>
            @endif

            {{-- Submission Info --}}
            <div style="display: flex; gap: 24px; font-size: 13px; color: #6b7280; justify-content: center; flex-wrap: wrap;">
                <div>Started: <strong>{{ $attempt->start_time->format('g:ia') }}</strong></div>
                <div>Submitted: <strong>{{ $attempt->submit_time ? $attempt->submit_time->format('g:ia') : '—' }}</strong></div>
                <div>Status: <strong>{{ $attempt->is_auto_submit ? 'Auto-submitted' : 'Manual' }}</strong></div>
            </div>
        </div>

        {{-- Question Review --}}
        <div class="bento-card">
            <h3 style="margin-bottom: 16px;">Question Review</h3>

            @foreach ($questions as $index => $question)
                @php
                    $selectedAnswerId = $studentAnswers[$question->question_id] ?? null;
                    $correctAnswer = $question->answers->firstWhere('is_correct', true);
                    $wasCorrect = $selectedAnswerId && $correctAnswer && $selectedAnswerId == $correctAnswer->answer_id;
                @endphp
                <div style="padding: 16px; border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 12px;
                    {{ $wasCorrect ? 'background: #f0fdf4;' : ($selectedAnswerId ? 'background: #fef2f2;' : 'background: #f9fafb;') }}">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
                        <div style="flex: 1;">
                            <p style="font-weight: 600; margin: 0 0 8px 0;">
                                {{ $index + 1 }}. {{ $question->question_text }}
                            </p>
                            @if ($selectedAnswerId)
                                @php
                                    $selected = $question->answers->firstWhere('answer_id', $selectedAnswerId);
                                @endphp
                                <p style="margin: 0; font-size: 14px;">
                                    Your answer: <strong>{{ $selected->answer_text ?? 'Unknown' }}</strong>
                                    @if ($correctAnswer)
                                        <span style="margin-left: 8px;">
                                            @if ($wasCorrect)
                                                <span style="color: #16a34a;">&#10003; Correct</span>
                                            @else
                                                <span style="color: #dc2626;">&#10007; Incorrect</span>
                                                (Correct: {{ $correctAnswer->answer_text }})
                                            @endif
                                        </span>
                                    @endif
                                </p>
                            @else
                                <p style="margin: 0; font-size: 14px; color: #dc2626;">
                                    &#10007; Not answered
                                </p>
                            @endif
                        </div>
                        <div style="font-size: 13px; font-weight: 600; white-space: nowrap;">
                            {{ $question->marks }} mark{{ $question->marks > 1 ? 's' : '' }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="text-align: center;">
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                <span class="material-symbols-outlined">dashboard</span>
                Back to Dashboard
            </a>
        </div>

    </div>
</div>
@endsection
