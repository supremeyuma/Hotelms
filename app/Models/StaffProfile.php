<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'position',
        'phone',
        'meta',
        'action_code_hash',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /* ---------------- Relationships ---------------- */

    // Belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /* ---------------- Mutators ---------------- */

    // Hash staff action codes
    public function setActionCodeHashAttribute($value)
    {
        if ($value) {
            $this->attributes['action_code_hash'] = bcrypt($value);
        }
    }

    /* ---------------- Scopes ---------------- */

    public function scopeByPosition($query, $position)
    {
        return $query->where('position', $position);
    }
}
