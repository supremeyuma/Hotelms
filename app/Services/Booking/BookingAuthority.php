<?php

namespace App\Services\Booking;

use App\Exceptions\ArrivalInsideLocalWindow;
use App\Exceptions\ArrivalOutsideLocalWindow;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Single authority decision for a booking.
 *
 * Spec section 4.2: arrivals inside the cutoff belong to the LAN/front desk;
 * later arrivals route online. Every booking write path asks this service
 * rather than computing a cutoff of its own.
 */
class BookingAuthority
{
    public const SETTING_KEY = 'booking_local_authority_cutoff_hours';

    public const DEFAULT_CUTOFF_HOURS = 48;

    public const CACHE_KEY = 'booking_authority_cutoff_hours';

    /**
     * Authority of this node. Read from configuration so one code path serves
     * cloud and LAN deployments.
     */
    public function localAuthority(): bool
    {
        return config('sync.node_type') !== 'cloud';
    }

    public function cutoffHours(): int
    {
        $hours = (int) Cache::remember(
            self::CACHE_KEY,
            300,
            fn () => Setting::get(self::SETTING_KEY, self::DEFAULT_CUTOFF_HOURS),
        );

        return $hours > 0 ? $hours : self::DEFAULT_CUTOFF_HOURS;
    }

    /**
     * The latest instant that is still inside the local window.
     *
     * bookings.check_in is cast to date, so the arrival has no time component.
     * End of day is the most inclusive reading available: it claims the whole
     * day for the local channel rather than guessing an hour. See Decision 2.
     *
     * Do NOT startOfDay() this. With cutoff 48 and now = 14:00, that yields
     * 10-08 00:00, which also claims the day after tomorrow (a 48-72h arrival
     * that belongs to the cloud). endOfDay on the arrival side is what makes
     * the comparison correct.
     */
    public function windowEnd(): CarbonInterface
    {
        return now()->addHours($this->cutoffHours());
    }

    public function isLocalWindow(CarbonInterface $checkIn): bool
    {
        return $checkIn->copy()->endOfDay()->lte($this->windowEnd());
    }

    /**
     * A human-readable description of the effective window.
     *
     * The window is 48-72h with the default cutoff, not 48h exactly, because
     * the arrival date has no time. Operators reading sync:status or the health
     * output need the range, not the setting value.
     */
    public function effectiveWindowDescription(): string
    {
        return $this->cutoffHours() . '-' . ($this->cutoffHours() + 24) . ' hours (date-only check_in)';
    }

    public function authorityFor(CarbonInterface $checkIn): string
    {
        return $this->isLocalWindow($checkIn) ? 'local' : 'cloud';
    }

    /**
     * Assert an online booking is allowed to be created.
     *
     * @throws ArrivalInsideLocalWindow
     */
    public function assertOnlineBookable(CarbonInterface $checkIn): void
    {
        if ($this->localAuthority() || ! $this->isLocalWindow($checkIn)) {
            return;
        }

        throw new ArrivalInsideLocalWindow($checkIn, $this->cutoffHours());
    }

    /**
     * Assert a front-desk booking is allowed to be created.
     *
     * Spec section 8: the desk books now and tomorrow; beyond the cutoff the
     * online channel is authoritative and the desk hands the guest over.
     *
     * @throws ArrivalOutsideLocalWindow
     */
    public function assertFrontDeskBookable(CarbonInterface $checkIn): void
    {
        if ($this->isLocalWindow($checkIn)) {
            return;
        }

        throw new ArrivalOutsideLocalWindow($checkIn, $this->cutoffHours());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
