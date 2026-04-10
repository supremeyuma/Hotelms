<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    public const SOURCE_PUBLIC = 'public';
    public const SOURCE_GUEST = 'guest';
    public const SOURCE_STAFF = 'staff';

    public const STATUS_NEW = 'new';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'feedback';

    protected $fillable = [
        'source',
        'category',
        'status',
        'subject',
        'message',
        'rating',
        'is_anonymous',
        'allow_follow_up',
        'contact_name',
        'contact_email',
        'contact_phone',
        'submitted_by_user_id',
        'booking_id',
        'room_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'internal_notes',
        'metadata',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'allow_follow_up' => 'boolean',
        'metadata' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
