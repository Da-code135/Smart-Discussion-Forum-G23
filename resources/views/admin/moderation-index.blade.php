@extends('layouts.app')

@section('title', 'Moderation Panel')
@section('admin')

@section('content')
<div class="page-stack">
    <div class="admin-header">
        <h1>Moderation Panel</h1>
        <p>Review flagged content and remove posts that violate forum guidelines.</p>
    </div>

    {{-- Reported Topics --}}
    @if ($reportedTopics->isNotEmpty())
        <section class="card page-stack" style="margin-bottom: 2rem;">
            <div class="section-header">
                <div>
                    <h2>Reported Topics ({{ $reportedTopics->count() }})</h2>
                    <p>Entire discussion threads that have been flagged for review.</p>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Topic</th>
                            <th>Group</th>
                            <th>Created by</th>
                            <th>Reports</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reportedTopics as $topic)
                            <tr>
                                <td>
                                    <strong>{{ $topic->title }}</strong>
                                </td>
                                <td>{{ $topic->group->group_name ?? '—' }}</td>
                                <td>{{ optional($topic->creator)->full_name ?? 'Deleted User' }}</td>
                                <td><span class="badge badge-warning">{{ $topic->reports_count }}</span></td>
                                <td>
                                    <div class="table-actions">
                                        <a href="{{ route('forum.show', $topic->id) }}" class="btn btn-secondary btn-sm">View</a>
                                        <form method="POST" action="{{ route('admin.moderation.ignore-topic', $topic) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Dismiss all reports for this topic?')">Dismiss Reports</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Reported Posts --}}
    <section class="card page-stack">
        <div class="section-header">
            <div>
                <h2>Reported Posts {{ $reportedTopics->isNotEmpty() ? '(' . $reportedPosts->count() . ')' : '' }}</h2>
                <p>Individual replies that have been flagged for review.</p>
            </div>
        </div>

        @forelse ($reportedPosts as $post)
            <div class="reported-post-card" style="padding: 1rem; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 1rem;">
                <p>
                    <strong>{{ $post->user->full_name }}</strong>
                    in "<a href="{{ route('forum.show', $post->topic_id) }}">{{ $post->topic->title }}</a>"
                </p>
                <p>{{ Str::limit($post->content, 200) }}</p>
                <small>{{ $post->created_at->format('M j, Y') }}</small>

                <div class="form-actions-row" style="margin-top: 0.75rem;">
                    <form method="POST" action="{{ route('admin.moderation.remove', $post) }}">
                        @csrf
                        <textarea name="reason" placeholder="Reason for removal (optional)" rows="2" style="width: 100%; margin-bottom: 0.5rem;" class="form-input"></textarea>
                        <button type="submit" class="btn btn-danger btn-sm">Remove Post</button>
                    </form>

                    <form method="POST" action="{{ route('admin.moderation.ignore', $post) }}" style="margin-left: 0.5rem;">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">Ignore Report</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <span class="material-symbols-outlined" style="font-size: 40px;">verified</span>
                <p>No reported posts. All clear!</p>
            </div>
        @endforelse
    </section>
</div>
@endsection
