<?php
// app/Models/InventoryLocation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLocation extends Model
{
    protected $fillable = ['name', 'type'];

    public function stocks()
    {
        return $this->hasMany(InventoryStock::class);
    }
}
