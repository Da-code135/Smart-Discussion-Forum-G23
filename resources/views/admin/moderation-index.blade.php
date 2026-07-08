@extends('layouts.app')

@section('title', 'Moderation Panel')
@section('activeNav', 'admin-moderation')
@section('admin')

@section('content')
<div class="container">
    <div class="admin-header">
        <h1>Moderation Panel — Reported Posts</h1>
        <p>Review flagged content and remove posts that violate forum guidelines.</p>
    </div>

    @forelse ($reportedPosts as $post)
        <div class="reported-post-card">
            <p>
                <strong>{{ $post->user->full_name }}</strong>
                in "{{ $post->topic->title }}"
            </p>
            <p>{{ Str::limit($post->content, 200) }}</p>
            <small>{{ $post->created_at->format('M j, Y') }}</small>

            <div class="actions">
                <form method="POST" action="{{ route('admin.moderation.remove', $post) }}">
                    @csrf
                    <textarea name="reason" placeholder="Reason for removal (optional)" rows="2"></textarea>
                    <button type="submit" class="btn btn-danger">Remove Post</button>
                </form>

                <form method="POST" action="{{ route('admin.moderation.ignore', $post) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Ignore Report</button>
                </form>
            </div>
        </div>
    @empty
        <div class="reported-post-card">
            <p>No reported posts.</p>
        </div>
    @endforelse
</div>
@endsection
