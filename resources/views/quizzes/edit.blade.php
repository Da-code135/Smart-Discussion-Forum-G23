@extends('layouts.app')

@section('title', 'Edit Quiz: ' . $quiz->title)

@section('content')
<div class="page-stack">
    <div class="page-header">
        <div class="page-header-row">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <a href="{{ route('quizzes.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
                    <h1 style="margin: 0;">{{ $quiz->title }}</h1>
                    @if ($quiz->published_at)
                        <span class="badge badge-success">Published</span>
                    @else
                        <span class="badge badge-warning">Draft</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
        {{-- Left: Quiz Details + Questions --}}
        <div class="page-stack">
            {{-- Quiz Details Form --}}
            <div class="card">
                <div class="card-header">Quiz Details</div>
                <div class="card-body">
                    <form action="{{ route('quizzes.update', $quiz->quiz_id) }}" method="POST" class="form-stack">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" name="title" class="form-input" value="{{ $quiz->title }}" required>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="scheduled_date" class="form-label">Date</label>
                                <input type="date" id="scheduled_date" name="scheduled_date" class="form-input" value="{{ $quiz->scheduled_date }}" required>
                            </div>
                            <div class="form-group">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" id="start_time" name="start_time" class="form-input" value="{{ $quiz->start_time instanceof \Carbon\Carbon ? $quiz->start_time->format('H:i') : substr($quiz->start_time, 0, 5) }}" required>
                            </div>
                            <div class="form-group">
                                <label for="duration_minutes" class="form-label">Duration (min)</label>
                                <input type="number" id="duration_minutes" name="duration_minutes" class="form-input" value="{{ $quiz->duration_minutes }}" required>
                            </div>
                        </div>

                        {{-- Configuration Options --}}
                        <div class="card" style="background: var(--bg-muted, #f8f9fa); padding: 1rem;">
                            <h3 style="margin-top: 0;">Quiz Settings</h3>

	                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
	                                <label style="display: flex; align-items: center; gap: 0.5rem;">
	                                    <input type="hidden" name="lock_screen_on_start" value="0">
	                                    <input type="checkbox" name="lock_screen_on_start" value="1" {{ $quiz->configuration?->lock_screen_on_start ? 'checked' : '' }}>
	                                    <span>Lock screen during quiz (prevent cheating)</span>
	                                </label>

	                                <label style="display: flex; align-items: center; gap: 0.5rem;">
	                                    <input type="hidden" name="show_results_after_close" value="0">
	                                    <input type="checkbox" name="show_results_after_close" value="1" {{ $quiz->configuration?->show_results_after_close ? 'checked' : '' }}>
	                                    <span>Show results after quiz closes</span>
	                                </label>

	                                <label style="display: flex; align-items: center; gap: 0.5rem;">
	                                    <input type="hidden" name="show_correct_answers" value="0">
	                                    <input type="checkbox" name="show_correct_answers" value="1" {{ $quiz->configuration?->show_correct_answers ? 'checked' : '' }}>
	                                    <span>Show correct answers with results</span>
	                                </label>

	                                <label style="display: flex; align-items: center; gap: 0.5rem;">
	                                    <input type="hidden" name="allow_late_join" value="0">
	                                    <input type="checkbox" name="allow_late_join" value="1" {{ $quiz->configuration?->allow_late_join ? 'checked' : '' }}>
	                                    <span>Allow late joiners (but no extra time)</span>
	                                </label>
	                            </div>

                            <div class="form-group" style="margin-top: 1rem;">
                                <label for="participation_criteria" class="form-label">How to award participation marks?</label>
                                <textarea id="participation_criteria" name="participation_criteria" rows="2" class="form-input" placeholder="E.g., Full marks if attempted and score >= 50%">{{ $quiz->configuration?->participation_criteria }}</textarea>
                            </div>
                        </div>

                        <div class="form-actions" style="justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Questions Section --}}
            <div class="card">
                <div class="card-header">
                    <h2 style="margin: 0;">Questions ({{ $quiz->questions->count() }})</h2>
                </div>
                <div class="card-body page-stack">
                    @if ($quiz->questions->isEmpty())
                        <div class="empty-state">
                            <p>No questions yet. Add one below.</p>
                        </div>
                    @else
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            @foreach ($quiz->questions as $question)
                                <div class="card" style="padding: 1rem; border: 1px solid var(--border-color);">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                        <div style="flex: 1;">
                                            <strong>Q{{ $question->question_order }}: {{ $question->question_text }}</strong>
                                            <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem; color: var(--text-muted);">
                                                Type: <span class="badge badge-secondary">{{ $question->question_type }}</span>
                                                Marks: <strong>{{ $question->marks }}</strong>
                                            </p>

                                            {{-- Show answers --}}
                                            @if ($question->answers->isNotEmpty())
                                        <div style="margin-top: 0.5rem; padding-left: 1rem; border-left: 2px solid var(--border-color);">
                                            @foreach ($question->answers as $answer)
                                                <div style="margin: 0.25rem 0; font-size: 0.875rem; display: flex; align-items: center; gap: 0.25rem;">
                                                    <span>{{ $answer->is_correct ? '✓' : '○' }}</span>
                                                    <span>{{ $answer->answer_text }}</span>
                                                    <form method="POST" action="{{ route('answers.destroy', $answer->answer_id) }}" style="display: inline;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm" style="font-size: 0.7rem; padding: 0.1rem 0.4rem;" onclick="return confirm('Delete this answer?')">x</button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                            {{-- Add Answer Form --}}
                                            <form method="POST" action="{{ route('answers.store', $question->question_id) }}" style="display: flex; gap: 0.5rem; margin-top: 0.5rem; align-items: center;">
                                                @csrf
                                                <input type="text" name="answer_text" placeholder="Add answer option..." class="form-input" style="flex: 1;" required>
                                                <label style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.8rem; white-space: nowrap;">
                                                    <input type="checkbox" name="is_correct" value="1"> Correct
                                                </label>
                                                <button type="submit" class="btn btn-secondary btn-sm">Add</button>
                                            </form>
                                        </div>

                                        <form method="POST" action="{{ route('questions.destroy', $question->question_id) }}" style="margin-left: 0.5rem;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this question and all its answers?')">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Add Question Form --}}
                    <div class="card" style="padding: 1rem; border: 1px dashed var(--border-color);">
                        <h3 style="margin-top: 0;">Add New Question</h3>
                        <form action="{{ route('quizzes.questions.store', $quiz->quiz_id) }}" method="POST" class="form-stack">
                            @csrf

                            <div class="form-group">
                                <label for="question_text" class="form-label">Question *</label>
                                <textarea id="question_text" name="question_text" class="form-input" placeholder="Type your question..." required></textarea>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label for="question_type" class="form-label">Type</label>
                                    <select id="question_type" name="question_type" class="form-input" required>
                                        <option value="MCQ">Multiple Choice (MCQ)</option>
                                        <option value="TF">True/False</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="marks" class="form-label">Marks</label>
                                    <input type="number" id="marks" name="marks" class="form-input" value="1" min="1" required>
                                </div>
                            </div>

                            <div class="form-actions" style="justify-content: flex-end;">
                                <button type="submit" class="btn btn-primary">Add Question</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Quiz Info Sidebar --}}
        <div>
            <div class="card" style="position: sticky; top: 1rem;">
                <div class="card-header">Quiz Info</div>
                <div class="card-body page-stack" style="font-size: 0.9rem;">
                    <div>
                        <strong>Scheduled:</strong><br>
                        {{ $quiz->scheduled_date instanceof \Carbon\Carbon ? $quiz->scheduled_date->format('M d, Y') : \Carbon\Carbon::parse($quiz->scheduled_date)->format('M d, Y') }} @ {{ $quiz->start_time instanceof \Carbon\Carbon ? $quiz->start_time->format('H:i') : substr($quiz->start_time, 0, 5) }}
                    </div>

                    <div>
                        <strong>Duration:</strong><br>
                        {{ $quiz->duration_minutes }} minutes
                    </div>

                    <div>
                        <strong>Target:</strong><br>
                        {{ $quiz->target_category }}s
                    </div>

                    <div>
                        <strong>Status:</strong><br>
                        @if ($quiz->published_at)
                            <span class="badge badge-success">Published</span><br>
                            <small style="color: var(--text-muted);">Announced {{ $quiz->published_at->diffForHumans() }}</small>
                        @else
                            <span class="badge badge-warning">Draft</span><br>
                            <small style="color: var(--text-muted);">Not yet announced</small>
                        @endif
                    </div>

                    {{-- Publish Button --}}
                    @if (!$quiz->published_at && $quiz->questions->count() > 0)
                        <form action="{{ route('quizzes.publish', $quiz->quiz_id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success" style="width: 100%;">
                                Publish as Announcement
                            </button>
                        </form>
                        <small style="color: var(--text-muted);">Students will be notified</small>
                    @elseif ($quiz->published_at)
                        <p style="font-size: 0.8rem; color: var(--text-muted);">Already announced to {{ $quiz->target_category }}s</p>
                    @else
                        <p style="font-size: 0.8rem; color: #dc3545;">Add at least 1 question before publishing</p>
                    @endif

                    {{-- Delete Button --}}
                    @if (!$quiz->published_at)
                        <form action="{{ route('quizzes.destroy', $quiz->quiz_id) }}" method="POST" onsubmit="return confirm('Delete this quiz? All questions and answers will be lost.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm" style="width: 100%;">Delete Quiz</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
