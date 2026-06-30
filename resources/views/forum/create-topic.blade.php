@extends('layouts.app')

@section('title', 'Create Topic')
@section('activeNav', 'topics')

@section('content')
<header class="page-header">
    <div class="page-header-row">
        <div>
            <h1>Create a New Topic</h1>
            <p>Start a discussion or ask a question in your group forum</p>
        </div>
        <a href="{{ route('forum.index') }}" class="btn btn-tertiary">
            <span class="material-symbols-outlined" style="font-size: 1.25rem;">arrow_back</span>
            Back to Forum
        </a>
    </div>
</header>

<section class="mb-4">
    <div class="bento-card profile-form-card" style="max-width: 720px;">
        <form method="POST" action="{{ route('forum.store') }}">
            @csrf

            {{-- Topic Title --}}
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="title" class="form-label">
                    Topic Title <span class="text-danger" style="color: var(--danger, #dc3545);">*</span>
                </label>
                <input type="text"
                       id="title"
                       name="title"
                       value="{{ old('title') }}"
                       maxlength="255"
                       required
                       placeholder="e.g., How do I use a for loop in Python?"
                       class="form-input @error('title') is-invalid @enderror">
                @error('title')
                    <p class="form-error" style="color: var(--danger, #dc3545); font-size: 0.875rem; margin-top: 0.25rem;">
                        <span class="material-symbols-outlined" style="font-size: 0.875rem; vertical-align: middle;">error</span>
                        {{ $message }}
                    </p>
                @enderror
                <p class="form-hint" style="color: rgba(88, 103, 75, 0.6); font-size: 0.8rem; margin-top: 0.25rem;">
                    Choose a clear, descriptive title. Max 255 characters.
                </p>
            </div>

            {{-- Post Type --}}
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label">Topic Type</label>
                <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                    <label class="radio-card" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; border: 1px solid rgba(0,0,0,0.1); border-radius: 0.5rem; cursor: pointer; flex: 1;">
                        <input type="radio" name="post_type" value="discussion" {{ old('post_type', 'discussion') === 'discussion' ? 'checked' : '' }}>
                        <div>
                            <strong>Discussion</strong>
                            <p style="margin: 0; font-size: 0.8rem; color: rgba(88, 103, 75, 0.7);">Open conversation and opinions</p>
                        </div>
                    </label>
                    <label class="radio-card" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; border: 1px solid rgba(0,0,0,0.1); border-radius: 0.5rem; cursor: pointer; flex: 1;">
                        <input type="radio" name="post_type" value="question" {{ old('post_type') === 'question' ? 'checked' : '' }}>
                        <div>
                            <strong>Question</strong>
                            <p style="margin: 0; font-size: 0.8rem; color: rgba(88, 103, 75, 0.7);">Seek a specific answer</p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Description --}}
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="description" class="form-label">
                    Description <span class="text-danger" style="color: var(--danger, #dc3545);">*</span>
                </label>
                <textarea id="description"
                          name="description"
                          rows="6"
                          required
                          maxlength="10000"
                          placeholder="Describe your topic in detail. What are you trying to discuss or ask?"
                          class="form-input @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                @error('description')
                    <p class="form-error" style="color: var(--danger, #dc3545); font-size: 0.875rem; margin-top: 0.25rem;">
                        <span class="material-symbols-outlined" style="font-size: 0.875rem; vertical-align: middle;">error</span>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Submit --}}
            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <a href="{{ route('forum.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined" style="font-size: 1.25rem;">add_comment</span>
                    Create Topic
                </button>
            </div>
        </form>
    </div>
</section>
@endsection
