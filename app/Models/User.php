<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    protected $fillable = [
        'uuid',
        'role_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /* ---------------- Relationships ---------------- */

    // A user belongs to a role
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // A user may have a staff profile
    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    // A user may have many bookings
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // Orders made by user (guest or staff)
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Staff audit logs
    public function audits()
    {
        return $this->hasMany(AuditLog::class);
    }

    /* ---------------- Mutators ---------------- */

    // Automatically hash passwords
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = bcrypt($value);
        }
    }

    /* ---------------- Scopes ---------------- */

    // Filter staff
    public function scopeStaff($query)
    {
        return $query->whereHas('staffProfile');
    }
}
