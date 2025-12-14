<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Image model — polymorphic media for Rooms, RoomTypes, Facilities, etc.
 *
 * Fields:
 * - id
 * - imageable_id
 * - imageable_type
 * - path
 * - caption
 * - is_primary
 * - meta (json)
 * - timestamps
 */
class Image extends Model
{
    use SoftDeletes;

    protected $table = 'images';

    protected $fillable = [
        'imageable_id',
        'imageable_type',
        'path',
        'caption',
        'is_primary',
        'meta',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'meta' => 'array',
    ];

    /**
     * Polymorphic relation to owning model.
     */
    public function imageable()
    {
        return $this->morphTo();
    }

    /**
     * Accessor for full URL (public disk).
     */
    public function getUrlAttribute(): ?string
    {
        if (! $this->path) return null;
        return Storage::disk('public')->url($this->path);
    }


    /**
     * Helper for thumbnail URL — looks for a thumb variant or falls back to original.
     */
    public function getThumbUrlAttribute(): ?string
    {
        // convention: thumbnails saved in same folder with suffix _thumb
        $thumbPath = preg_replace('/(\.\w+)$/', '_thumb$1', $this->path);
        if (Storage::disk('public')->exists($thumbPath)) {
            return Storage::disk('public')->url($thumbPath);
        }
        return $this->url;
    }
}
