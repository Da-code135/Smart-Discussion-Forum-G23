<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $topic->title }} — Export</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; line-height: 1.6; color: #1a1a1a; }
        h1 { font-size: 20px; color: #2c3e50; margin: 0 0 0.5rem; }
        h2 { font-size: 14px; color: #58674b; margin: 0 0 1rem; font-weight: normal; }
        .header { text-align: center; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 1px solid #ddd; }
        .post { border: 1px solid #ddd; padding: 12px; margin-bottom: 12px; border-radius: 4px; page-break-inside: avoid; }
        .post-meta { color: #666; font-size: 11px; margin-bottom: 8px; }
        .post-author { font-weight: bold; color: #34495e; }
        .post-content { white-space: pre-wrap; }
        .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #ddd; text-align: center; font-size: 10px; color: #888; }
        .empty { color: #666; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <h2>Forum Topic Export</h2>
        <p style="margin: 0; color: #666;">Exported on {{ now()->format('Y-m-d H:i') }}</p>
    </div>

    <div class="post">
        <div class="post-meta">
            <span class="post-author">{{ $topic->creator->full_name }}</span>
            &middot; {{ $topic->created_at->format('Y-m-d H:i') }}
            &middot; {{ ucfirst($topic->post_type) }}
        </div>
        <h1>{{ $topic->title }}</h1>
        <div class="post-content">{!! nl2br(e($topic->description)) !!}</div>
    </div>

    @forelse ($replies as $reply)
        <div class="post">
            <div class="post-meta">
                <span class="post-author">{{ $reply->user->full_name }}</span>
                &middot; {{ $reply->created_at->format('Y-m-d H:i') }}
            </div>
            <div class="post-content">{!! nl2br(e($reply->content)) !!}</div>
        </div>
    @empty
        <p class="empty">No replies in this thread.</p>
    @endforelse

    <div class="footer">
        <p>Exported by {{ $exportedBy->full_name }} ({{ $exportedBy->email }})</p>
        <p>Group: {{ $topic->group->group_name ?? 'N/A' }} &middot; Topic ID: {{ $topic->id }}</p>
    </div>
</body>
</html>
