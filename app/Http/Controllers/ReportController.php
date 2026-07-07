<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Utilities\ReportUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct(
        protected ReportUtility $reportUtility
    ) {}

    /**
     * Store a new report.
     *
     * POST /report
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
            'type' => 'required|in:topic,post,reply',
            'id' => 'required|integer',
        ]);

        try {
            $this->reportUtility->createReport(
                Auth::user(),
                $validated['type'],
                $validated['id'],
                $validated['reason']
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Thank you for reporting this content. Our moderators will review it shortly.');
    }

    /**
     * Display a listing of the user's own reports.
     */
    public function index()
    {
        $reports = $this->reportUtility->getUserReports(Auth::user());

        return view('reports.index', compact('reports'));
    }
}
