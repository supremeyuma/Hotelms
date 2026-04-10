<?php
// app/Models/InventoryLocation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLocation extends Model
{
    public const TYPE_MAIN_STORE = 'main_store';
    public const TYPE_KITCHEN = 'kitchen';
    public const TYPE_BAR = 'bar';
    public const TYPE_LAUNDRY = 'laundry';
    public const TYPE_HOUSEKEEPING = 'housekeeping';

    public const TYPES = [
        self::TYPE_MAIN_STORE,
        self::TYPE_KITCHEN,
        self::TYPE_BAR,
        self::TYPE_LAUNDRY,
        self::TYPE_HOUSEKEEPING,
    ];

    protected $fillable = ['name', 'type'];

    public static function options(): array
    {
        return collect(self::TYPES)->map(fn (string $type) => [
            'value' => $type,
            'label' => str($type)->replace('_', ' ')->title()->toString(),
        ])->all();
    }

    public function stocks()
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
