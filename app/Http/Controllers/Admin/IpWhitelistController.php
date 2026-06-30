<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminIpWhitelist;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class IpWhitelistController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display IP whitelist
     */
    public function index()
    {
        $ips = AdminIpWhitelist::with('createdBy')
            ->latest()
            ->paginate(20);

        return view('admin.ip-whitelist.index', compact('ips'));
    }

    /**
     * Show form to add IP
     */
    public function create()
    {
        return view('admin.ip-whitelist.create');
    }

    /**
     * Store new IP
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ip_address' => 'required|ip|unique:admin_ip_whitelist,ip_address',
            'description' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $ip = AdminIpWhitelist::addIp(
            ip: $validated['ip_address'],
            description: $validated['description'] ?? null,
            expiresAt: $validated['expires_at'] ?? null,
            createdBy: auth()->id()
        );

        $this->auditLogService->log(
            action: 'admin.ip.added',
            target: $ip,
            description: auth()->user()->full_name . " added IP {$ip->ip_address} to whitelist"
        );

        return redirect()->route('admin.ip-whitelist.index')
            ->with('success', 'IP address added to whitelist successfully');
    }

    /**
     * Show form to edit IP
     */
    public function edit(AdminIpWhitelist $ipWhitelist)
    {
        return view('admin.ip-whitelist.edit', compact('ipWhitelist'));
    }

    /**
     * Update IP
     */
    public function update(Request $request, AdminIpWhitelist $ipWhitelist)
    {
        $validated = $request->validate([
            'ip_address' => 'required|ip|unique:admin_ip_whitelist,ip_address,' . $ipWhitelist->id,
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'expires_at' => 'nullable|date',
        ]);

        $oldValues = $ipWhitelist->only(['ip_address', 'description', 'is_active', 'expires_at']);

        $ipWhitelist->update($validated);

        $this->auditLogService->log(
            action: 'admin.ip.updated',
            target: $ipWhitelist,
            oldValues: $oldValues,
            newValues: $ipWhitelist->only(['ip_address', 'description', 'is_active', 'expires_at'])
        );

        return redirect()->route('admin.ip-whitelist.index')
            ->with('success', 'IP address updated successfully');
    }

    /**
     * Remove IP
     */
    public function destroy(AdminIpWhitelist $ipWhitelist)
    {
        $ipAddress = $ipWhitelist->ip_address;
        
        $this->auditLogService->log(
            action: 'admin.ip.removed',
            target: $ipWhitelist,
            oldValues: ['ip_address' => $ipAddress]
        );

        $ipWhitelist->delete();

        return redirect()->route('admin.ip-whitelist.index')
            ->with('success', 'IP address removed from whitelist');
    }

    /**
     * Activate IP
     */
    public function activate(AdminIpWhitelist $ipWhitelist)
    {
        $ipWhitelist->activate();

        $this->auditLogService->log(
            action: 'admin.ip.activated',
            target: $ipWhitelist,
            description: auth()->user()->full_name . " activated IP {$ipWhitelist->ip_address}"
        );

        return redirect()->route('admin.ip-whitelist.index')
            ->with('success', 'IP address activated');
    }

    /**
     * Deactivate IP
     */
    public function deactivate(AdminIpWhitelist $ipWhitelist)
    {
        $ipWhitelist->deactivate();

        $this->auditLogService->log(
            action: 'admin.ip.deactivated',
            target: $ipWhitelist,
            description: auth()->user()->full_name . " deactivated IP {$ipWhitelist->ip_address}"
        );

        return redirect()->route('admin.ip-whitelist.index')
            ->with('success', 'IP address deactivated');
    }
}
