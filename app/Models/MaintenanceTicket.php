<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id', 'staff_id', 'title', 'description', 'status', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function getGuestNameAttribute(): ?string
    {
        return $this->meta['guest_name'] ?? null;
    }

    public function getIssueTypeAttribute(): ?string
    {
        return $this->meta['issue_type'] ?? null;
    }

    public function getPhotoPathAttribute(): ?string
    {
        return $this->meta['photo_path'] ?? null;
    }
}
