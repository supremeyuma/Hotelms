<?php
// app/Services/AuditLoggerService.php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * AuditLoggerService
 *
 * Centralized service for recording and querying audit logs.
 */
class AuditLoggerService
{
    /**
     * Record an audit entry.
     *
     * @param string $action Short action key, e.g. 'booking_created'
     * @param Model|string $model Either model instance or model name
     * @param int|null $modelId Model primary key id
     * @param array $metadata Arbitrary metadata (before/after etc.)
     * @param Request|null $request Optional request to capture IP/user agent
     * @return AuditLog
     */
    public function log(string $action, $model, ?int $modelId = null, array $metadata = [], ?Request $request = null)
    {
        $user = Auth::user();

        $entry = AuditLog::create([
            'user_id'   => $user?->id,
            'action'    => $action,
            'model'     => $model instanceof Model ? get_class($model) : (string)$model,
            'model_id'  => $model instanceof Model ? $model->getKey() : $modelId,
            'ip_address'=> $request?->ip() ?? request()->ip(),
            'metadata'  => $metadata,
            'created_at'=> Carbon::now(),
        ]);

        return $entry;
    }

    /**
     * Query audit logs with filters and pagination.
     *
     * @param array $filters ['user_id','model','date_from','date_to','action']
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function query(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $q = AuditLog::query()->latest();

        if (!empty($filters['user_id'])) {
            $q->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['model'])) {
            $q->where('model', $filters['model']);
        }
        if (!empty($filters['action'])) {
            $q->where('action', $filters['action']);
        }
        if (!empty($filters['date_from'])) {
            $q->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $q->paginate($perPage);
    }

    /**
     * Shorthand for logging before/after model changes.
     *
     * @param string $action
     * @param Model $model
     * @param array $before
     * @param array $after
     * @param Request|null $request
     * @return AuditLog
     */
    public function logChange(string $action, Model $model, array $before = [], array $after = [], ?Request $request = null)
    {
        return $this->log($action, $model, $model->getKey(), [
            'before' => $before,
            'after' => $after,
        ], $request);
    }
}
