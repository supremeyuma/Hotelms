<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'room_id',
        'staff_id',
        'title',
        'description',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /* ---------------- Relationships ---------------- */

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /* ---------------- Scopes ---------------- */

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
