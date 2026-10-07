<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;

class ConflictPolicy extends SyncPolicy
{
    /**
     * Statuses meaning the money was taken and kept.
     *
     * Taken from the literals the application actually writes: PaymentController
     * and FlutterwaveService set 'paid', 'successful', 'success' and
     * 'completed'.
     */
    private const SETTLED_CAPTURED = ['paid', 'captured', 'successful', 'success', 'completed'];

    /**
     * Statuses meaning the money went back or never arrived.
     *
     * FlutterwaveService and EventService write 'refunded' and 'reversed';
     * 'failed' and 'cancelled' are the other terminal negatives in use.
     */
    private const SETTLED_REVERSED = ['refunded', 'reversed', 'failed', 'cancelled', 'canceled'];

    /**
     * Class 4: block and surface.
     *
     * Two rows that claim the same scarce resource are not merged by rule, they
     * are handed to a human. Returning a ConflictResult stops the applier from
     * writing either row until someone resolves it.
     *
     * When no conflict is found the rows still have to land somewhere: two
     * distinct bookings on different rooms both survive, and two nodes that saw
     * the same row without disagreeing take the newer one.
     */
    public function merge(array $local, array $remote): array|ConflictResult
    {
        $conflict = $this->detect($local, $remote);

        if ($conflict !== null) {
            return $conflict;
        }

        if (($local['sync_row_key'] ?? null) !== ($remote['sync_row_key'] ?? null)) {
            return [$local, $remote];
        }

        // Same row, no disagreement about what it means: the newer write wins.
        return (new MergePolicy())->merge($local, $remote);
    }

    private function detect(array $local, array $remote): ?ConflictResult
    {
        return match ($this->entityKey) {
            'bookings' => $this->overlappingReservation($local, $remote),
            'pos_sessions' => $this->doubleClaimedDevice($local, $remote),
            'payments' => $this->capturedThenRefunded($local, $remote),
            // Constructed directly rather than through SyncScope. Try every
            // rule instead of guessing; each only fires on its own shape.
            default => $this->overlappingReservation($local, $remote)
                ?? $this->doubleClaimedDevice($local, $remote)
                ?? $this->capturedThenRefunded($local, $remote),
        };
    }

    /**
     * Two bookings for the same room across overlapping dates.
     *
     * Reuses the comparison at RoomAvailabilityService rather than restating it.
     */
    private function overlappingReservation(array $local, array $remote): ?ConflictResult
    {
        if (($local['sync_row_key'] ?? null) === ($remote['sync_row_key'] ?? null)) {
            return null;
        }

        if (! $this->intervalsOverlap(
            (string) ($local['check_in'] ?? ''),
            (string) ($local['check_out'] ?? ''),
            (string) ($remote['check_in'] ?? ''),
            (string) ($remote['check_out'] ?? ''),
        )) {
            return null;
        }

        return new ConflictResult($local, $remote, 'overlapping_reservation');
    }

    /**
     * Two open sessions on one physical device.
     *
     * The column is `device_id`, not `pos_device_id`: it is a foreign Ulid to
     * `pos_devices`. A session that is still open carries a null `closed_at`,
     * which has no upper bound and so overlaps anything else on that device.
     */
    private function doubleClaimedDevice(array $local, array $remote): ?ConflictResult
    {
        if (($local['sync_row_key'] ?? null) === ($remote['sync_row_key'] ?? null)) {
            return null;
        }

        $device = (string) ($local['device_id'] ?? '');

        if ($device === '' || $device !== (string) ($remote['device_id'] ?? '')) {
            return null;
        }

        if (! $this->intervalsOverlap(
            (string) ($local['opened_at'] ?? ''),
            (string) ($local['closed_at'] ?? ''),
            (string) ($remote['opened_at'] ?? ''),
            (string) ($remote['closed_at'] ?? ''),
        )) {
            return null;
        }

        return new ConflictResult($local, $remote, 'device_already_in_use');
    }

    /**
     * One node took the money and another returned it.
     *
     * This is the same row seen on two nodes, so a different row key is not the
     * question. Two rows only disagree when both have reached a terminal state
     * and those states point opposite ways; 'pending' against 'paid' is a normal
     * progression and never blocks.
     */
    private function capturedThenRefunded(array $local, array $remote): ?ConflictResult
    {
        if (($local['sync_row_key'] ?? null) !== ($remote['sync_row_key'] ?? null)) {
            return null;
        }

        $localStatus = strtolower((string) ($local['status'] ?? ''));
        $remoteStatus = strtolower((string) ($remote['status'] ?? ''));

        if ($localStatus === $remoteStatus) {
            return null;
        }

        $opposed = in_array($localStatus, self::SETTLED_CAPTURED, true)
            && in_array($remoteStatus, self::SETTLED_REVERSED, true);

        $opposed = $opposed
            || (in_array($localStatus, self::SETTLED_REVERSED, true)
                && in_array($remoteStatus, self::SETTLED_CAPTURED, true));

        if (! $opposed) {
            return null;
        }

        return new ConflictResult($local, $remote, 'captured_then_refunded');
    }

    /**
     * Do the two half-open intervals [start, end) intersect?
     *
     * An empty end means the interval is still running and has no upper bound,
     * so it meets everything after its start. An empty start means nothing is
     * known about when the interval began, which proves nothing - returning
     * false here is deliberate, because a missing timestamp is not evidence of
     * an overlap and blocking on absent data would raise conflicts out of
     * incomplete rows rather than out of disagreement.
     */
    private function intervalsOverlap(string $localStart, string $localEnd, string $remoteStart, string $remoteEnd): bool
    {
        if ($localStart === '' || $remoteStart === '') {
            return false;
        }

        $localEnd = $localEnd === '' ? '9999-12-31 23:59:59' : $localEnd;
        $remoteEnd = $remoteEnd === '' ? '9999-12-31 23:59:59' : $remoteEnd;

        return $localStart < $remoteEnd && $remoteStart < $localEnd;
    }
}
