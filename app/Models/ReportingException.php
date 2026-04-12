<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingException extends Model
{
    protected $table = 'reporting_exceptions';
    protected $fillable = [
        'exception_type',
        'severity',
        'department',
        'room_id',
        'booking_id',
        'reference_type',
        'reference_id',
        'status',
        'assigned_to_id',
        'detected_at',
        'acknowledged_at',
        'resolved_at',
        'title',
        'description',
        'meta',
        'escalated',
        'escalated_at',
        'escalated_to_id',
        'resolution_notes',
        'follow_up_action',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'escalated_at' => 'datetime',
            'escalated' => 'boolean',
            'meta' => 'json',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to_id');
    }

    public static function createFromReference($referenceType, $referenceId, $exceptionType, $severity, $title, $description, $meta = [])
    {
        $department = self::determineDepartment($referenceType);

        return self::create([
            'exception_type' => $exceptionType,
            'severity' => $severity,
            'department' => $department,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'status' => 'open',
            'detected_at' => now(),
            'title' => $title,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    private static function determineDepartment($referenceType)
    {
        return match ($referenceType) {
            'MaintenanceTicket' => 'maintenance',
            'LaundryOrder' => 'laundry',
            'Order' => 'kitchen', // or 'bar' depending on context
            default => 'operations',
        };
    }

    public function markForEscalation($escalatedToId, $reason = null)
    {
        $this->update([
            'escalated' => true,
            'escalated_at' => now(),
            'escalated_to_id' => $escalatedToId,
            'follow_up_action' => $reason,
        ]);
    }

    public function acknowledge($userId)
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'assigned_to_id' => $userId,
        ]);
    }

    public function resolve($notes)
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }
}
