<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id',
        'title',
        'max_occupancy',
        'base_price',
        'features',
    ];

    protected $casts = [
        'features' => 'array',
        'base_price' => 'decimal:2',
    ];

    /* ---------------- Relationships ---------------- */

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }



    public function images()
    {
        return $this->morphMany(Image::class, 'imageable')->orderByDesc('is_primary')->orderBy('created_at');
    }

    /**
     * Primary image helper
     */
    public function primaryImage()
    {
        return $this->morphOne(Image::class, 'imageable')->where('is_primary', true);
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $img = $this->images()->where('is_primary', true)->first();
        if ($img) return $img->url;
        $first = $this->images()->first();
        return $first?->url;
    }

    /* ---------------- Scopes ---------------- */

    public function scopeAffordable($query, $maxPrice)
    {
        return $query->where('base_price', '<=', $maxPrice);
    }
}
