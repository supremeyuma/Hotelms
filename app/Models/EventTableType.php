<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventTableType extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'capacity',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
