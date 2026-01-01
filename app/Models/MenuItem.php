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
        'service_area'
    ];

    public function category()
    {
        return $this->belongsTo(MenuCategory::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(MenuSubcategory::class);
    }
}
