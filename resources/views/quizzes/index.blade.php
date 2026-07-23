@extends('layouts.app')

@section('title', 'My Quizzes')

@section('content')
<div class="page-stack">
    <div class="page-header">
        <div class="page-header-row">
            <div>
                <h1>{{ Auth::user()->isSystemAdmin() ? 'All Quizzes' : 'My Quizzes' }}</h1>
                <p>{{ Auth::user()->isSystemAdmin() ? 'Platform-wide quiz oversight across all groups.' : 'Create and manage quizzes for your students.' }}</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="{{ route('quizzes.results') }}" class="btn btn-secondary">
                    <span class="material-symbols-outlined">bar_chart</span>
                    Results
                </a>
                <a href="{{ route('quizzes.create') }}" class="btn btn-primary">
                    <span class="material-symbols-outlined">add</span>
                    Create Quiz
                </a>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($quizzes->isEmpty())
        <div class="empty-state">
            <span class="material-symbols-outlined" style="font-size: 40px;">quiz</span>
            <h2>No quizzes yet</h2>
            <p>Create your first quiz to get started.</p>
            <a href="{{ route('quizzes.create') }}" class="btn btn-primary">Create Quiz</a>
        </div>
    @else
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Group</th>
                        @if (Auth::user()->isSystemAdmin())
                            <th>Lecturer</th>
                        @endif
                        <th>Target</th>
                        <th>Scheduled</th>
                        <th>Duration</th>
                        <th>Questions</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quizzes as $quiz)
                        <tr>
                            <td><strong>{{ $quiz->title }}</strong></td>
                            <td><span class="badge badge-info">{{ $quiz->group->group_name ?? 'General' }}</span></td>
                            @if (Auth::user()->isSystemAdmin())
                                <td>{{ $quiz->lecturer->full_name ?? 'Unknown' }}</td>
                            @endif
                            <td><span class="badge badge-secondary">{{ $quiz->target_category }}</span></td>
                            <td>{{ $quiz->scheduled_date instanceof \Carbon\Carbon ? $quiz->scheduled_date->format('M d, Y') : \Carbon\Carbon::parse($quiz->scheduled_date)->format('M d, Y') }} @ {{ $quiz->start_time instanceof \Carbon\Carbon ? $quiz->start_time->format('H:i') : substr($quiz->start_time, 0, 5) }}</td>
                            <td>{{ $quiz->duration_minutes }} min</td>
                            <td>{{ $quiz->questions()->count() }}</td>
                            <td>
                                @if ($quiz->published_at)
                                    <span class="badge badge-success">Published</span>
                                @else
                                    <span class="badge badge-warning">Draft</span>
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('quizzes.edit', $quiz->quiz_id) }}" class="btn btn-primary btn-sm">Edit</a>
                                    @if (!$quiz->published_at)
                                        <form method="POST" action="{{ route('quizzes.destroy', $quiz->quiz_id) }}" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this quiz?')">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $quizzes->links() }}
        </div>
    @endif
</div>
@endsection
