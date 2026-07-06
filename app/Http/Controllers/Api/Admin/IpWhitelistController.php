<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminIpWhitelist;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IpWhitelistController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * GET /api/v1/admin/ip-whitelist
     * List all whitelisted IPs (System Admin only)
     */
    public function index()
    {
        // Authorization check - System Admin only
        if (! auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can access IP whitelist',
            ], 403);
        }

        $ips = AdminIpWhitelist::with('createdBy')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $ips->items(),
            'pagination' => [
                'total' => $ips->total(),
                'per_page' => $ips->perPage(),
                'current_page' => $ips->currentPage(),
                'last_page' => $ips->lastPage(),
                'from' => $ips->firstItem(),
                'to' => $ips->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/ip-whitelist/{ipId}
     * Get specific IP whitelist entry (System Admin only)
     */
    public function show($ipId)
    {
        // Authorization check - System Admin only
        if (! auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can access IP whitelist',
            ], 403);
        }

        $ip = AdminIpWhitelist::with('createdBy')->findOrFail($ipId);

        return response()->json([
            'success' => true,
            'data' => $ip,
        ]);
    }

    /**
     * POST /api/v1/admin/ip-whitelist
     * Add IP to whitelist (System Admin only)
     */
    public function store(Request $request)
    {
        // Authorization check - System Admin only
        if (! auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can manage IP whitelist',
            ], 403);
        }

        $validated = $request->validate([
            'ip_address' => 'required|ip|unique:admin_ip_whitelist,ip_address',
            'description' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $ip = AdminIpWhitelist::addIp(
            ip: $validated['ip_address'],
            description: $validated['description'] ?? null,
            expiresAt: $validated['expires_at'] ?? null,
            createdBy: Auth::id()
        );

        // Audit log
        $this->auditLogService->log(
            action: 'admin.ip.added',
            target: $ip,
            description: auth()->user()->full_name." added IP {$ip->ip_address} to whitelist"
        );

        return response()->json([
            'success' => true,
            'message' => 'IP address added to whitelist successfully',
            'data' => $ip,
        ], 201);
    }

    /**
     * PUT /api/v1/admin/ip-whitelist/{ipId}
     * Update IP whitelist entry (System Admin only)
     */
    public function update(Request $request, $ipId)
    {
        // Authorization check - System Admin only
        if (! auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can manage IP whitelist',
            ], 403);
        }

        $ip = AdminIpWhitelist::findOrFail($ipId);

        $validated = $request->validate([
            'ip_address' => 'required|ip|unique:admin_ip_whitelist,ip_address,'.$ip->id,
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'expires_at' => 'nullable|date',
        ]);

        $oldValues = $ip->only(['ip_address', 'description', 'is_active', 'expires_at']);

        $ip->update($validated);

        // Audit log
        $this->auditLogService->log(
            action: 'admin.ip.updated',
            target: $ip,
            oldValues: $oldValues,
            newValues: $ip->only(['ip_address', 'description', 'is_active', 'expires_at'])
        );

        return response()->json([
            'success' => true,
            'message' => 'IP address updated successfully',
            'data' => $ip,
        ]);
    }

    /**
     * DELETE /api/v1/admin/ip-whitelist/{ipId}
     * Remove IP from whitelist (System Admin only)
     */
    public function destroy($ipId)
    {
        // Authorization check - System Admin only
        if (! auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can manage IP whitelist',
            ], 403);
        }

        $ip = AdminIpWhitelist::findOrFail($ipId);

        // Audit log
        $this->auditLogService->log(
            action: 'admin.ip.removed',
            target: $ip,
            oldValues: ['ip_address' => $ip->ip_address]
        );

        $ip->delete();

        return response()->json([
            'success' => true,
            'message' => 'IP address removed from whitelist',
        ]);
    }

    /**
     * POST /api/v1/admin/ip-whitelist/{ipId}/activate
     * Activate IP whitelist entry (System Admin only)
     */
    public function activate($ipId)
    {
        // Authorization check - System Admin only
        if (! auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can manage IP whitelist',
            ], 403);
        }

        $ip = AdminIpWhitelist::findOrFail($ipId);
        $ip->activate();

        // Audit log
        $this->auditLogService->log(
            action: 'admin.ip.activated',
            target: $ip,
            description: auth()->user()->full_name." activated IP {$ip->ip_address}"
        );

        return response()->json([
            'success' => true,
            'message' => 'IP address activated',
            'data' => $ip,
        ]);
    }

    /**
     * POST /api/v1/admin/ip-whitelist/{ipId}/deactivate
     * Deactivate IP whitelist entry (System Admin only)
     */
    public function deactivate($ipId)
    {
        // Authorization check - System Admin only
        if (! auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can manage IP whitelist',
            ], 403);
        }

        $ip = AdminIpWhitelist::findOrFail($ipId);
        $ip->deactivate();

        // Audit log
        $this->auditLogService->log(
            action: 'admin.ip.deactivated',
            target: $ip,
            description: auth()->user()->full_name." deactivated IP {$ip->ip_address}"
        );

        return response()->json([
            'success' => true,
            'message' => 'IP address deactivated',
            'data' => $ip,
        ]);
    }

    /**
     * GET /api/v1/admin/ip-whitelist/check/{ip}
     * Check if IP is whitelisted (System Admin only)
     */
    public function check($ip)
    {
        // Authorization check - System Admin only
        if (! auth()->user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only System Administrators can check IP whitelist',
            ], 403);
        }

        $isAllowed = AdminIpWhitelist::isIpAllowed($ip);

        return response()->json([
            'success' => true,
            'data' => [
                'ip' => $ip,
                'is_allowed' => $isAllowed,
            ],
        ]);
    }
}
