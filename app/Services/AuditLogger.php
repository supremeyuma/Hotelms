<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * AuditLogger - Static wrapper for AuditLoggerService
 * 
 * Provides convenient static access to audit logging functionality.
 */
class AuditLogger
{
    /**
     * Record an audit entry.
     *
     * @param string $action Short action key, e.g. 'booking_created'
     * @param Model|string $model Either model instance or model name
     * @param int|null $modelId Model primary key id
     * @param array $metadata Arbitrary metadata (before/after etc.)
     * @param Request|null $request Optional request to capture IP/user agent
     * @return \App\Models\AuditLog
     */
    public static function log(string $action, $model, ?int $modelId = null, array $metadata = [], ?Request $request = null)
    {
        return app(AuditLoggerService::class)->log($action, $model, $modelId, $metadata, $request);
    }

    /**
     * Query audit logs with filters and pagination.
     *
     * @param array $filters ['user_id','model','date_from','date_to','action']
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public static function query(array $filters = [], int $perPage = 50)
    {
        return app(AuditLoggerService::class)->query($filters, $perPage);
    }
}
