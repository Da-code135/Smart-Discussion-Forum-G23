<?php

namespace App\Http\Controllers\Api\Admin;

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

    /**
     * GET /api/v1/admin/system-config
     * Get all system configuration (System Admin only)
     */
    public function index()
    {
        // Authorization check - System Admin only
        if (!auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can access system configuration'
            ], 403);
        }

        $configs = SystemConfig::all();

        // Convert to key-value pairs for easier access
        $configArray = [];
        foreach ($configs as $config) {
            $configArray[$config->config_key] = $config->config_value;
        }

        return response()->json([
            'success' => true,
            'data' => $configArray,
        ]);
    }

    /**
     * PUT /api/v1/admin/system-config
     * Update system configuration (System Admin only)
     */
    public function update(Request $request)
    {
        // Authorization check - System Admin only
        if (!auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can update system configuration'
            ], 403);
        }

        $validated = $request->validate([
            'max_login_attempts' => 'sometimes|required|integer|min:1',
            'lockout_minutes' => 'sometimes|required|integer|min:1',
            'inactivity_warning_days' => 'sometimes|required|integer|min:1',
            'blacklist_duration_days' => 'sometimes|required|integer|min:1',
        ]);

        foreach ($validated as $key => $value) {
            SystemConfig::updateOrCreate(
                ['config_key' => $key],
                ['config_value' => $value]
            );
        }

        // Audit log
        $this->auditLogService->logSystemConfigUpdated($validated);

        return response()->json([
            'success' => true,
            'message' => 'System configuration updated successfully',
            'data' => $validated,
        ]);
    }

    /**
     * GET /api/v1/admin/system-config/{key}
     * Get specific configuration value (System Admin only)
     */
    public function show($key)
    {
        // Authorization check - System Admin only
        if (!auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can access system configuration'
            ], 403);
        }

        $config = SystemConfig::where('config_key', $key)->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration key not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'key' => $config->config_key,
                'value' => $config->config_value,
            ],
        ]);
    }
}
