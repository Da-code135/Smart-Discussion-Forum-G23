<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Topic Export</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
        h1, h2 { color: #2c3e50; }
        .header { text-align: center; margin-bottom: 30px; }
        .topic { margin-bottom: 30px; }
        .post { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
        .post-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .post-author { font-weight: bold; color: #34495e; }
        .post-date { color: #7f8c8d; font-size: 0.9em; }
        .post-content { margin-top: 10px; }
        .footer { text-align: center; font-size: 0.8em; color: #888; margin-top: 40px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name') }} Forum Export</h1>
        <p>Exported on {{ now()->format('Y-m-d H:i') }}</p>
    </div>

    <div class="topic">
        <div class="post">
            <div class="post-header">
                <div class="post-author">{{ $topic->creator->full_name }}</div>
                <div class="post-date">{{ $topic->created_at->format('Y-m-d H:i') }}</div>
            </div>
            <h2>{{ $topic->title }}</h2>
            <div class="post-content">
                {!! nl2br(e($topic->description)) !!}
            </div>
        </div>

        @foreach($replies as $reply)
            <div class="post">
                <div class="post-header">
                    <div class="post-author">{{ $reply->user->full_name }}</div>
                    <div class="post-date">{{ $reply->created_at->format('Y-m-d H:i') }}</div>
                </div>
                <div class="post-content">
                    {!! nl2br(e($reply->content)) !!}
                </div>
            </div>
        @endforeach
    </div>

    <div class="footer">
        <p>Exported by {{ Auth::user()->full_name }} ({{ Auth::user()->email }})</p>
        <p>Topic ID: {{ $topic->id }}</p>
    </div>
</body>
</html>