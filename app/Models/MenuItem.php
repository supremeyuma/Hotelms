<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_category_id',
        'menu_subcategory_id',
        'name',
        'description',
        'price',
        'prep_time_minutes',
        'is_available',
        'service_area',
        'image_path',
    ];

    public function category()
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(MenuSubcategory::class, 'menu_subcategory_id');
    }

    public function images()
    {
        return $this->hasMany(MenuItemImage::class);
    }
    public function getEffectivePrepTimeAttribute(): int
    {
        return
            ($this->prep_time_minutes ?? 0)
            + ($this->subcategory?->prep_time_minutes ?? 0)
            + ($this->category?->prep_time_minutes ?? 0);
    }

}
