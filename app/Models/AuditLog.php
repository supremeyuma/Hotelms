<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Sync\Syncable;

class AuditLog extends Model
{
    use HasFactory, Syncable;

    protected $fillable = [
        'user_id',
        'action',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /* ---------------- Relationships ---------------- */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /* ---------------- Scopes ---------------- */

    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }
}
