<?php

// app/Models/LaundryItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaundryItem extends Model
{
    protected $fillable = [
        'name',
        'price',
        'description',
    ];

    public function orderItems()
    {
        return $this->hasMany(LaundryOrderItem::class);
    }
}
