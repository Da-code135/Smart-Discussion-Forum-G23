@extends('layouts.app')

@section('title', 'Edit Topic')
@section('activeNav', 'topics')

@section('content')
<div class="page-stack">
    <header class="page-header">
        <div class="page-header-row">
            <div>
                <h1>Edit topic</h1>
                <p>Update the title, description, or type of your topic.</p>
            </div>
            <a href="{{ route('forum.show', $topic->id) }}" class="back-link">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to topic
            </a>
        </div>
    </header>

    <section class="profile-form-card" style="max-width: 760px; margin: 0 auto; width: 100%;">
        <form method="POST" action="{{ route('forum.update', $topic->id) }}" class="form-stack">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="title" class="form-label">Topic title</label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    value="{{ old('title', $topic->title) }}"
                    maxlength="255"
                    required
                    class="form-input @error('title') is-invalid @enderror"
                >
                @error('title')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Topic type</label>
                <div class="form-grid-2">
                    <label class="card clickable-card" style="padding: 18px; cursor: pointer;">
                        <input type="radio" name="post_type" value="discussion" {{ old('post_type', $topic->post_type) === 'discussion' ? 'checked' : '' }}>
                        <strong>Discussion</strong>
                        <p>Open conversation, reflection, or shared ideas.</p>
                    </label>
                    <label class="card clickable-card" style="padding: 18px; cursor: pointer;">
                        <input type="radio" name="post_type" value="question" {{ old('post_type', $topic->post_type) === 'question' ? 'checked' : '' }}>
                        <strong>Question</strong>
                        <p>Ask for a specific answer or explanation.</p>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="8"
                    required
                    maxlength="10000"
                    class="form-input @error('description') is-invalid @enderror"
                >{{ old('description', $topic->description) }}</textarea>
                @error('description')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions-row" style="justify-content: flex-end;">
                <a href="{{ route('forum.show', $topic->id) }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined">save</span>
                    Save changes
                </button>
            </div>
        </form>
    </section>
</div>
@endsection
