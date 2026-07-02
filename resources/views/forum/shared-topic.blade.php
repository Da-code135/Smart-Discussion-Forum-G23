@extends('layouts.guest')

@section('content')
<div class="container">
    <div class="text-center mb-5">
        <h1 class="mb-3">Shared Topic</h1>
        <p>This topic was shared with you by {{ $sharingUser->full_name }} ({{ $sharingUser->email }})</p>
    </div>

    <div class="topic">
        <div class="post mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="fw-bold">{{ $topic->creator->full_name }}</div>
                <div class="text-muted">{{ $topic->created_at->format('Y-m-d H:i') }}</div>
            </div>
            <h2>{{ $topic->title }}</h2>
            <div class="mt-3">
                {!! nl2br(e($topic->description)) !!}
            </div>
        </div>

        @foreach($replies as $reply)
            <div class="post mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="fw-bold">{{ $reply->user->full_name }}</div>
                    <div class="text-muted">{{ $reply->created_at->format('Y-m-d H:i') }}</div>
                </div>
                <div>
                    {!! nl2br(e($reply->content)) !!}
                </div>
            </div>
        @endforeach
    </div>

    <div class="text-center mt-5">
        <p class="text-muted">
            This is a shared view of a topic from the {{ config('app.name') }} discussion forum.
        </p>
    </div>
</div>
@endsection