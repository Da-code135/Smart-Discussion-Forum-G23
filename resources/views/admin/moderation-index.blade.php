@extends('layouts.app')

@section('title', 'Moderation Panel')
@section('activeNav', 'admin-moderation')

@section('content')
<div class="page-stack" style="max-width: 900px;">
    <div class="page-header">
        <h1>Moderation Panel</h1>
        <p>Review flagged content and remove posts that violate forum guidelines.</p>
    </div>

    @forelse ($reportedPosts as $post)
        <div class="card">
            <div class="card-body" style="padding: 20px;">
                <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px;">
                    <span class="material-symbols-outlined" style="color: var(--color-error, #e53e3e); font-size: 28px;">flag</span>
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px;">
                            <strong>{{ $post->user->full_name }}</strong>
                            <span class="badge badge-secondary">Reported</span>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">{{ $post->created_at->format('M j, Y \\a\\t g:i A') }}</span>
                        </div>
                        <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0;">
                            in topic: <a href="{{ route('forum.show', $post->topic_id) }}" target="_blank">{{ $post->topic->title }}</a>
                        </p>
                    </div>
                </div>

                <div style="background: var(--surface); border-radius: 8px; padding: 16px; margin-bottom: 16px; border: 1px solid var(--border);">
                    <p style="margin: 0; white-space: pre-wrap;">{{ Str::limit($post->content, 400) }}</p>
                </div>

                <div style="display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap;">
                    <form method="POST" action="{{ route('admin.moderation.remove', $post) }}" style="flex: 1; min-width: 200px;">
                        @csrf
                        <div style="margin-bottom: 8px;">
                            <textarea name="reason" placeholder="Reason for removal (optional)" rows="2"
                                      style="width: 100%; resize: vertical; padding: 8px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.875rem;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger" style="width: 100%;">
                            <span class="material-symbols-outlined" style="font-size: 16px;">delete</span>
                            Remove Post
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.moderation.ignore', $post) }}" style="min-width: 140px;">
                        @csrf
                        <button type="submit" class="btn btn-secondary" style="width: 100%;">
                            <span class="material-symbols-outlined" style="font-size: 16px;">close</span>
                            Ignore Report
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body" style="padding: 40px; text-align: center;">
                <span class="material-symbols-outlined" style="font-size: 48px; color: var(--text-muted); display: block; margin-bottom: 12px;">verified</span>
                <h2>All Clear</h2>
                <p style="color: var(--text-muted); margin: 0;">No reported posts.</p>
            </div>
        </div>
    @endforelse
</div>
@endsection
