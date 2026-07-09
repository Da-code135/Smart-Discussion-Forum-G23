@extends('layouts.app')

@section('title', 'Create Topic')
@section('activeNav', 'topics')

@section('content')
<div class="page-stack">
    <header class="page-header">
        <div class="page-header-row">
            <div>
                <h1>Create a new topic</h1>
                <p>Start a discussion or ask a focused academic question.</p>
            </div>
            <a href="{{ route('forum.index') }}" class="back-link">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to forum
            </a>
        </div>
    </header>

    <section class="profile-form-card" style="max-width: 760px; margin: 0 auto; width: 100%;">
        <form method="POST" action="{{ route('forum.store') }}" class="form-stack">
            @csrf

            <div class="form-group">
                <label for="title" class="form-label">Topic title</label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    value="{{ old('title') }}"
                    maxlength="255"
                    required
                    placeholder="e.g. How do I use a for loop in Python?"
                    class="form-input @error('title') is-invalid @enderror"
                >
                @error('title')
                    <p class="form-error">{{ $message }}</p>
                @enderror
                <p class="form-hint">Choose a clear title that classmates can scan quickly.</p>
            </div>

            <div class="form-group">
                <label class="form-label">Topic type</label>
                <div class="form-grid-2">
                    <label class="card clickable-card" style="padding: 18px; cursor: pointer;">
                        <input type="radio" name="post_type" value="discussion" {{ old('post_type', 'discussion') === 'discussion' ? 'checked' : '' }}>
                        <strong>Discussion</strong>
                        <p>Open conversation, reflection, or shared ideas.</p>
                    </label>
                    <label class="card clickable-card" style="padding: 18px; cursor: pointer;">
                        <input type="radio" name="post_type" value="question" {{ old('post_type') === 'question' ? 'checked' : '' }}>
                        <strong>Question</strong>
                        <p>Ask for a specific answer or explanation.</p>
                    </label>
                </div>
            </div>

            @if (isset($groups) && $groups->isNotEmpty())
                <div class="form-group">
                    <label for="group_id" class="form-label">Target group</label>
                    <select name="group_id" id="group_id" class="form-input @error('group_id') is-invalid @enderror">
                        <option value="">-- Select a group --</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" {{ old('group_id') == $group->id ? 'selected' : '' }}>
                                {{ $group->group_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('group_id')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                    <p class="form-hint">Choose which group this topic belongs to.</p>
                </div>
            @endif

            <div class="form-group">
                <label for="description" class="form-label">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="8"
                    required
                    maxlength="10000"
                    placeholder="Describe the topic in detail. What are you trying to discuss or ask?"
                    class="form-input @error('description') is-invalid @enderror"
                >{{ old('description') }}</textarea>
                @error('description')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions-row" style="justify-content: flex-end;">
                <a href="{{ route('forum.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined">add_comment</span>
                    Create topic
                </button>
            </div>
        </form>
    </section>
</div>
@endsection
