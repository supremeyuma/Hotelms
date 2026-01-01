<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuCategory extends Model
{
    protected $fillable = [
        'name', 'type', 'is_active', 'sort_order'
    ];

    public function subcategories()
    {
        return $this->hasMany(MenuSubcategory::class);
    }

    public function items()
    {
        return $this->hasMany(MenuItem::class);
    }
}
