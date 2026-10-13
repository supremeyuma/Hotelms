<?php

// app/Models/InventoryLocation.php

namespace App\Models;

use App\Sync\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLocation extends Model
{
    use HasFactory, Syncable;

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
