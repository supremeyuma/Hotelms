<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuSubcategory extends Model
{
    protected $fillable = [
        'menu_category_id', 'name', 'is_active', 'sort_order'
    ];

    public function category()
    {
        return $this->belongsTo(MenuCategory::class);
    }

    public function items()
    {
        return $this->hasMany(MenuItem::class);
    }
}
