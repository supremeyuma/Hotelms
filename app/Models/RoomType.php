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

    /* ---------------- Scopes ---------------- */

    public function scopeAffordable($query, $maxPrice)
    {
        return $query->where('base_price', '<=', $maxPrice);
    }
}
