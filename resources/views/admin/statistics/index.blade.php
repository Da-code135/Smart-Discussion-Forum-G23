@extends('layouts.app')

@section('title', 'Statistics Dashboard')

@section('content')
<div class="page-stack">
    <header class="page-header">
        <h1>Platform Statistics</h1>
        <p>Group engagement metrics visualized &mdash; click <strong>Recalculate</strong> on any group to pull live data.</p>
    </header>

    @include('admin.statistics.partials.stats-content')
</div>
@endsection
