<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    // #92: SHOW SYSTEM CONFIG (System Admin only)
    public function index()
    {
        // Authorization check - only System Admins can access system configuration
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can access system configuration');
        }

        $configs = SystemConfig::all();

        return view('admin.system-config.index', [
            'configs' => $configs,
        ]);
    }

    // #92: UPDATE SYSTEM CONFIG (System Admin only)
    public function update(Request $request)
    {
        // Authorization check - only System Admins can update system configuration
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can update system configuration');
        }

        $validated = $request->validate([
            'max_login_attempts' => 'required|integer|min:1',
            'lockout_minutes' => 'required|integer|min:1',
            'inactivity_warning_days' => 'required|integer|min:1',
            'warning_response_days' => 'required|integer|min:1',
            'blacklist_duration_days' => 'required|integer|min:1',
            'days_before_second_warning' => 'required|integer|min:1',
            'days_before_blacklist' => 'required|integer|min:1',
            'classification_review_threshold' => 'required|integer|min:0|max:100',
            'quiz_late_join_allowed' => 'nullable|in:0,1',
        ]);

        // Unchecked checkboxes are absent from the request; persist an explicit 0/1
        $validated['quiz_late_join_allowed'] = $request->boolean('quiz_late_join_allowed') ? '1' : '0';

        foreach ($validated as $key => $value) {
            SystemConfig::updateOrCreate(
                ['config_key' => $key],
                ['config_value' => $value]
            );
        }

        // Clear cache for all updated config keys
        SystemConfig::clearAllCaches();

        // Audit log
        $this->auditLogService->logSystemConfigUpdated($validated);

        return redirect()->back()->with('success', 'System configuration updated');
    }
}
