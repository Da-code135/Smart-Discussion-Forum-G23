@extends('layouts.app')

@section('title', 'Create Quiz')

@section('content')
<div class="page-stack">
    <div class="page-header">
        <h1>Create New Quiz</h1>
        <p>Set up a new quiz for your students.</p>
    </div>

    <div class="card" style="max-width: 760px;">
        <div class="card-body">
            <form action="{{ route('quizzes.store') }}" method="POST" class="form-stack">
                @csrf

                {{-- Quiz Title --}}
                <div class="form-group">
                    <label for="title" class="form-label">Quiz Title *</label>
                    <input type="text"
                           id="title"
                           name="title"
                           class="form-input @error('title') is-invalid @enderror"
                           placeholder="E.g., Midterm Exam - Laravel Basics"
                           value="{{ old('title') }}"
                           required>
                    @error('title')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Description --}}
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description"
                              name="description"
                              rows="3"
                              class="form-input @error('description') is-invalid @enderror"
                              placeholder="What is this quiz about?">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Group Selector --}}
                <div class="form-group">
                    <label for="group_id" class="form-label">Target Group *</label>
                    <select id="group_id"
                            name="group_id"
                            class="form-input @error('group_id') is-invalid @enderror"
                            required>
                        <option value="">-- Select Group --</option>
                        @foreach($groups as $g)
                            <option value="{{ $g->id }}" {{ old('group_id') == $g->id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                        @endforeach
                    </select>
                    @error('group_id')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Target Category --}}
                <div class="form-group">
                    <label for="target_category" class="form-label">Who takes this quiz? *</label>
                    <select id="target_category"
                            name="target_category"
                            class="form-input @error('target_category') is-invalid @enderror"
                            required>
                        <option value="">-- Select Role --</option>
                        <option value="Student" {{ old('target_category') === 'Student' ? 'selected' : '' }}>Students Only</option>
                        <option value="Lecturer" {{ old('target_category') === 'Lecturer' ? 'selected' : '' }}>Lecturers Only</option>
                        <option value="Member" {{ old('target_category') === 'Member' ? 'selected' : '' }}>All Members</option>
                    </select>
                    <small class="form-text">Only users with this role will see the quiz announcement</small>
                    @error('target_category')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Scheduling --}}
                <div class="card" style="background: var(--bg-muted, #f8f9fa); padding: 1rem;">
                    <h3 style="margin-top: 0;">When should this quiz run?</h3>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                        {{-- Date --}}
                        <div class="form-group">
                            <label for="scheduled_date" class="form-label">Date *</label>
                            <input type="date"
                                   id="scheduled_date"
                                   name="scheduled_date"
                                   class="form-input @error('scheduled_date') is-invalid @enderror"
                                   value="{{ old('scheduled_date') }}"
                                   required>
                            @error('scheduled_date')
                                <p class="form-error" style="font-size: 0.8rem;">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Start Time --}}
                        <div class="form-group">
                            <label for="start_time" class="form-label">Start Time *</label>
                            <input type="time"
                                   id="start_time"
                                   name="start_time"
                                   class="form-input @error('start_time') is-invalid @enderror"
                                   value="{{ old('start_time') }}"
                                   required>
                            <small class="form-text">HH:MM (24-hour)</small>
                            @error('start_time')
                                <p class="form-error" style="font-size: 0.8rem;">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Duration --}}
                        <div class="form-group">
                            <label for="duration_minutes" class="form-label">Duration (minutes) *</label>
                            <input type="number"
                                   id="duration_minutes"
                                   name="duration_minutes"
                                   class="form-input @error('duration_minutes') is-invalid @enderror"
                                   placeholder="60"
                                   min="1"
                                   max="480"
                                   value="{{ old('duration_minutes', 60) }}"
                                   required>
                            <small class="form-text">1 minute to 8 hours</small>
                            @error('duration_minutes')
                                <p class="form-error" style="font-size: 0.8rem;">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="form-actions" style="justify-content: flex-end;">
                    <a href="{{ route('quizzes.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Quiz</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
