<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::latest()->paginate(50);

        return inertia('AuditLogs/Index', [
            'logs' => $logs
        ]);
    }
}
