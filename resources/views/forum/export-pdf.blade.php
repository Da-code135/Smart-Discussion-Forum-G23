<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $topic->title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            margin: 30px;
        }
        h1 {
            font-size: 22px;
            color: #586747;
            border-bottom: 2px solid #586747;
            padding-bottom: 8px;
            margin-bottom: 5px;
        }
        .export-meta {
            font-size: 10px;
            color: #888;
            margin-bottom: 25px;
            font-style: italic;
        }
        .opening-post {
            background-color: #f5f7f2;
            border-left: 4px solid #586747;
            padding: 15px;
            margin-bottom: 25px;
        }
        .opening-post .author-line {
            font-weight: bold;
            color: #586747;
            margin-bottom: 3px;
        }
        .opening-post .date {
            font-size: 10px;
            color: #888;
            margin-bottom: 10px;
        }
        .opening-post .content {
            white-space: pre-wrap;
        }
        .replies-header {
            font-size: 16px;
            color: #586747;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-top: 20px;
            margin-bottom: 15px;
        }
        .reply {
            border-left: 3px solid #ccc;
            padding: 10px 15px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .reply .author-line {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 2px;
        }
        .reply .date {
            font-size: 10px;
            color: #888;
            margin-bottom: 8px;
        }
        .reply .content {
            white-space: pre-wrap;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #aaa;
            text-align: center;
        }
    </style>
</head>
<body>
    {{-- Title --}}
    <h1>{{ $topic->title }}</h1>
    <p class="export-meta">
        Exported on {{ now()->format('M j, Y') }} by {{ Auth::user()->full_name }}
        &middot; Group: {{ $topic->group->group_name ?? 'N/A' }}
        @if ($topic->post_type === 'question')
            &middot; Type: Question
        @endif
    </p>

    {{-- Opening Post (Topic Description) --}}
    <div class="opening-post">
        <p class="author-line">{{ $topic->creator->full_name }}</p>
        <p class="date">{{ $topic->created_at->format('M j, Y \a\t g:ia') }}</p>
        <div class="content">{{ $topic->description }}</div>
    </div>

    {{-- Replies --}}
    <h2 class="replies-header">Replies ({{ $replies->count() }})</h2>

    @forelse ($replies as $reply)
        <div class="reply">
            <p class="author-line">{{ $reply->user->full_name }}</p>
            <p class="date">{{ $reply->created_at->format('M j, Y \a\t g:ia') }}</p>
            <div class="content">{{ $reply->content }}</div>
        </div>
    @empty
        <p style="color: #888; font-style: italic;">No replies in this topic.</p>
    @endforelse

    {{-- Footer --}}
    <div class="footer">
        Generated from Smart Discussion Forum &middot; {{ config('app.name') }}
    </div>
</body>
</html>
