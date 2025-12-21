<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function notes()
    {
        return $this->hasMany(StaffNote::class,'staff_id');
    }

     // Threads where this user is the staff
    public function threads()
    {
        return $this->hasMany(StaffThread::class, 'staff_id');
    }

    // Threads where this user is the admin who created them (optional)
    public function adminThreads()
    {
        return $this->hasMany(StaffThread::class, 'admin_id');
    }

    public function suspend()
    {
        $this->update(['suspended_at' => now()]);
    }

    public function reinstate()
    {
        $this->update(['suspended_at' => null]);
    }

    public function getIsSuspendedAttribute(): bool
    {
        return !is_null($this->suspended_at);
    }

    public function maintenanceTasks()
    {
        // Assuming your maintenance table has a 'user_id' or 'assigned_to' column
        // If your foreign key is named differently, e.g., 'staff_id', 
        // use: return $this->hasMany(MaintenanceTask::class, 'staff_id');
        
        return $this->hasMany(MaintenanceTicket::class, 'staff_id'); 
    }
    
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // app/Models/Room.php
    public function cleanings()
    {
        return $this->hasMany(RoomCleaning::class);
    }

    public function latestCleaning()
    {
        return $this->hasOne(RoomCleaning::class)->latestOfMany();
    }


}
