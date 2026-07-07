<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Report;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
            'type' => 'required|in:topic,post,reply',
            'id' => 'required|integer',
        ]);

        $class = match ($request->type) {
            'topic' => Topic::class,
            'post', 'reply' => Post::class,
        };

        $model = $class::findOrFail($request->id);

        // Group isolation: only report content in the user's own group
        if (method_exists($model, 'group') && $model->group) {
            if ($model->group->id !== Auth::user()->group_id && ! Auth::user()->isSystemAdmin()) {
                abort(403, 'You cannot report content outside your group.');
            }
        } elseif (isset($model->topic) && $model->topic->group_id !== Auth::user()->group_id && ! Auth::user()->isSystemAdmin()) {
            // Posts inherit group via their topic
            abort(403, 'You cannot report content outside your group.');
        }

        $report = new Report([
            'reason' => $request->reason,
            'user_id' => Auth::id(),
        ]);

        $model->reports()->save($report);

        return back()->with('success', 'Thank you for reporting this content. Our moderators will review it shortly.');
    }

    /**
     * Display a listing of the user's own reports.
     */
    public function index()
    {
        $reports = Report::where('user_id', Auth::id())->with('reportable')->latest()->paginate(20);

        return view('reports.index', compact('reports'));
    }
}
