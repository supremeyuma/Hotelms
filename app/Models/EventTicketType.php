<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventTicketType extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'quantity_available',
        'quantity_sold',
        'max_per_person',
        'is_active',
        'sales_start',
        'sales_end',
        'color_code',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity_available' => 'integer',
        'quantity_sold' => 'integer',
        'max_per_person' => 'integer',
        'is_active' => 'boolean',
        'sales_start' => 'datetime',
        'sales_end' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets()
    {
        return $this->hasMany(EventTicket::class, 'ticket_type_id');
    }

    public function getAvailableQuantityAttribute()
    {
        return $this->quantity_available - $this->quantity_sold;
    }

    public function getIsSoldOutAttribute()
    {
        return $this->available_quantity <= 0;
    }

    public function getIsOnSaleAttribute()
    {
        $now = now();
        return $this->is_active && 
               $this->sales_start && 
               $this->sales_end &&
               $now->between($this->sales_start, $this->sales_end) &&
               !$this->is_sold_out;
    }

    public function incrementSoldCount($quantity = 1)
    {
        $this->increment('quantity_sold', $quantity);
    }
}