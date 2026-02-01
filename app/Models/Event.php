<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description', 
        'start_datetime',
        'end_datetime',
        'image',
        'is_active',
        'is_featured',
        'venue',
        'capacity',
        'ticket_sales_start',
        'ticket_sales_end',
        'max_tickets_per_person',
        'has_table_reservations',
        'table_capacity',
        'table_price',
        'promotional_content',
        'status'
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'ticket_sales_start' => 'datetime',
        'ticket_sales_end' => 'datetime',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'has_table_reservations' => 'boolean',
        'table_price' => 'decimal:2',
        'promotional_content' => 'array',
        'status' => 'string',
    ];

    // --- Relationships ---

    public function ticketTypes()
    {
        return $this->hasMany(EventTicketType::class);
    }

    public function tickets()
    {
        return $this->hasMany(EventTicket::class);
    }

    public function tableReservations()
    {
        return $this->hasMany(EventTableReservation::class);
    }

    public function promotionalMedia()
    {
        return $this->hasMany(EventPromotionalMedia::class);
    }

    public function tableTypes()
    {
        return $this->hasMany(EventTableType::class);
    }

    // --- Accessors ---

    /**
     * Replaces old event_date logic by formatting the start_datetime
     */
    public function getFormattedDateAttribute()
    {
        return $this->start_datetime ? $this->start_datetime->format('F j, Y') : 'N/A';
    }

    /**
     * Replaces start_time and end_time logic by pulling time from datetimes
     */
    public function getFormattedTimeAttribute()
    {
        if (!$this->start_datetime || !$this->end_datetime) return 'N/A';
        
        return $this->start_datetime->format('g:i A') . ' - ' . $this->end_datetime->format('g:i A');
    }

    public function getIsSalesOpenAttribute()
    {
        $now = now();
        return $this->is_active && 
               $this->ticket_sales_start && 
               $this->ticket_sales_end &&
               $now->between($this->ticket_sales_start, $this->ticket_sales_end);
    }

    public function getTotalTicketsSoldAttribute()
    {
        return $this->tickets()->where('status', 'confirmed')->count();
    }

    public function getTotalTicketsRevenueAttribute()
    {
        return $this->tickets()
            ->where('status', 'confirmed')
            ->sum('amount_paid');
    }

    public function getTotalTablesReservedAttribute()
    {
        return $this->tableReservations()->where('status', 'confirmed')->count();
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Fixed: Now uses start_datetime
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_datetime', '>=', now());
    }

    /**
     * Fixed: Now uses start_datetime
     */
    public function scopePast($query)
    {
        return $query->where('start_datetime', '<', now());
    }
}