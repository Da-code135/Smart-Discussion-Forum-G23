<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display audit logs with filters
     */
    public function index(Request $request)
    {
        $filters = [
            'action' => $request->input('action'),
            'user_id' => $request->input('user_id'),
            'target_type' => $request->input('target_type'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        $logs = $this->auditLogService->getPaginatedLogs(20, $filters);

        // Get unique actions for filter dropdown
        $actions = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->map(function ($action) {
                return [
                    'value' => $action,
                    'label' => $this->getActionLabel($action),
                ];
            });

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => $filters,
        ]);
    }

    /**
     * Show audit log details
     */
    public function show(AuditLog $log)
    {
        $log->load('user');

        return view('admin.audit-logs.show', [
            'log' => $log,
        ]);
    }

    /**
     * Export audit logs
     */
    public function export(Request $request, string $format = 'json')
    {
        $filters = [
            'action' => $request->input('action'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        $logs = $this->auditLogService->exportLogs($filters);

        if ($format === 'csv') {
            return $this->exportCsv($logs);
        }

        return response()->json([
            'success' => true,
            'data' => $logs,
            'count' => count($logs),
        ]);
    }

    /**
     * Export logs as CSV
     */
    protected function exportCsv(array $logs)
    {
        $filename = 'audit-logs-' . now()->format('Y-m-d-His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            
            // CSV header
            fputcsv($file, ['ID', 'Timestamp', 'User', 'Action', 'Target Type', 'Target ID', 'Description', 'IP Address']);
            
            // CSV data
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log['id'],
                    $log['timestamp'],
                    $log['user'],
                    $log['action'],
                    $log['target_type'],
                    $log['target_id'],
                    $log['description'],
                    $log['ip_address'],
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get action label
     */
    protected function getActionLabel(string $action): string
    {
        $labels = [
            'user.role.changed' => 'User Role Changed',
            'user.group.changed' => 'User Group Changed',
            'user.blacklisted' => 'User Blacklisted',
            'user.blacklist.lifted' => 'User Blacklist Lifted',
            'user.warned' => 'User Warned',
            'user.activated' => 'User Activated',
            'user.deleted' => 'User Deleted',
            'group.created' => 'Group Created',
            'group.updated' => 'Group Updated',
            'group.deleted' => 'Group Deleted',
            'group.member.added' => 'Group Member Added',
            'group.member.removed' => 'Group Member Removed',
            'system.config.updated' => 'System Configuration Updated',
            'admin.ip.added' => 'Admin IP Added',
            'admin.ip.removed' => 'Admin IP Removed',
        ];

        return $labels[$action] ?? ucfirst(str_replace('.', ' ', $action));
    }
}
