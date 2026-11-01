<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A price band for annual licences, selected by the property's room count.
 *
 * Prices are monthly figures because the tiers are quoted that way; checkout
 * charges `monthly × term_months` once, for the whole year.
 */
class LicencePlan extends Model
{
    protected $fillable = [
        'name',
        'description',
        'min_rooms',
        'max_rooms',
        'monthly_naira',
        'monthly_usd',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_rooms' => 'integer',
        'max_rooms' => 'integer',
        'monthly_naira' => 'decimal:2',
        'monthly_usd' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public const CURRENCY_NAIRA = 'NGN';
    public const CURRENCY_USD = 'USD';

    public const CURRENCIES = [self::CURRENCY_NAIRA, self::CURRENCY_USD];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The tier a property of this size is charged at.
     *
     * Bands are inclusive and checked in sort order, so a gap in the configured
     * bands (a mistyped max) surfaces as "no plan matched" instead of silently
     * charging the cheapest tier.
     */
    public static function forRooms(int $rooms): ?self
    {
        return static::query()
            ->active()
            ->where('min_rooms', '<=', $rooms)
            ->where(function (Builder $query) use ($rooms) {
                $query->whereNull('max_rooms')->orWhere('max_rooms', '>=', $rooms);
            })
            ->orderBy('sort_order')
            ->orderBy('min_rooms')
            ->first();
    }

    public function monthlyFor(string $currency): float
    {
        return $currency === self::CURRENCY_USD
            ? (float) $this->monthly_usd
            : (float) $this->monthly_naira;
    }

    /**
     * Price of a whole term, charged in one payment.
     */
    public function termPrice(string $currency, int $months): float
    {
        return round($this->monthlyFor($currency) * $months, 2);
    }

    public function bandLabel(): string
    {
        if ($this->max_rooms === null) {
            return "{$this->min_rooms}+ rooms";
        }

        return $this->min_rooms === $this->max_rooms
            ? "{$this->min_rooms} rooms"
            : "{$this->min_rooms}–{$this->max_rooms} rooms";
    }
}
