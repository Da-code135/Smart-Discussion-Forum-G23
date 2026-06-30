<?php

namespace App\Http\Middleware;

use App\Models\AdminIpWhitelist;
use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;

class IpWhitelist
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if IP whitelisting is enabled
        $whitelistEnabled = config('security.ip_whitelist_enabled', false);
        
        if (!$whitelistEnabled) {
            return $next($request);
        }

        // Get client IP
        $clientIp = $request->ip();

        // Check if IP is in whitelist
        if (!AdminIpWhitelist::isIpAllowed($clientIp)) {
            // Log the unauthorized access attempt
            $this->auditLogService->log(
                action: 'admin.ip.blocked',
                description: "Blocked admin access attempt from IP: {$clientIp}",
                userId: null
            );

            // Return 403 Forbidden
            abort(403, "Access denied. Your IP address ({$clientIp}) is not authorized to access this area.");
        }

        return $next($request);
    }
}
