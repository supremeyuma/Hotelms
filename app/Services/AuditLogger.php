<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function log(string $action, string $model, $modelId, array $meta = [])
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action'  => $action,
            'model'   => $model,
            'model_id'=> $modelId,
            'metadata'=> $meta,
        ]);
    }
}
