<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Topic;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
            'type' => 'required|in:topic,post,reply',
            'id' => 'required|integer'
        ]);

        $class = match($request->type) {
            'topic' => Topic::class,
            'post', 'reply' => Post::class,
        };

        $model = $class::findOrFail($request->id);

        $report = new Report([
            'reason' => $request->reason,
            'user_id' => Auth::id()
        ]);

        $model->reports()->save($report);

        return back()->with('success', 'Thank you for reporting this content. Our moderators will review it shortly.');
    }
}