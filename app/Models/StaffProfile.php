<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\ActionCodeService;

class StaffProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'position',
        'phone',
        'meta',
        'action_code',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected $hidden = ['action_code'];

    protected $appends = ['action_code_plain'];

    public function getActionCodePlainAttribute(): string
    {
        // 1. Check if the value is null or empty first
        if (!$this->action_code) {
            return '123ABC';
        }

        // 2. Wrap in a try-catch to handle old invalid encryption payloads
        try {
            return ActionCodeService::decrypt($this->action_code);
        } catch (\Exception $e) {
            return 'Invalid Code';
        }
    }

    /* ---------------- Relationships ---------------- */

    // Belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /* ---------------- Mutators ---------------- */

    // Hash staff action codes

    /* ---------------- Scopes ---------------- */

    public function scopeByPosition($query, $position)
    {
        return $query->where('position', $position);
    }
}
