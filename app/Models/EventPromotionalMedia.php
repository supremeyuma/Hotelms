<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventPromotionalMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'media_type',
        'media_url',
        'title',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function getIsImageAttribute()
    {
        return $this->media_type === 'image';
    }

    public function getIsVideoAttribute()
    {
        return $this->media_type === 'video';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}