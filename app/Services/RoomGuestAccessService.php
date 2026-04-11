<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomAccessToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RoomGuestAccessService
{
    public function __construct(
        protected QrCodeService $qrCodeService
    ) {
    }

    public function ensureRoomQr(Room $room, bool $forceRegenerate = false): Room
    {
        if ($forceRegenerate || blank($room->qr_key)) {
            $room->forceFill([
                'qr_key' => Str::random(48),
                'qr_generated_at' => now(),
                'qr_invalidated_at' => null,
            ])->save();
        }

        return $room->fresh();
    }

    public function invalidateRoomQr(Room $room): Room
    {
        $room->forceFill([
            'qr_key' => null,
            'qr_invalidated_at' => now(),
        ])->save();

        return $room->fresh();
    }

    public function entryUrl(Room $room): ?string
    {
        if (blank($room->qr_key)) {
            return null;
        }

        return route('guest.room.entry', ['room' => $room->qr_key]);
    }

    public function generateQrSvg(Room $room, int $size = 420): string
    {
        if (blank($room->qr_key)) {
            throw new \RuntimeException('Room QR code has not been generated.');
        }

        $svg = $this->qrCodeService->generateSvg(
            $this->entryUrl($room),
            $size
        );

        // Browsers can reject SVGs if anything precedes the XML declaration.
        // Returning bare <svg> markup is more tolerant for inline viewing/downloads.
        $svg = preg_replace('/^\s*<\?xml[^>]+\?>\s*/', '', $svg) ?? $svg;

        return ltrim($svg, "\xEF\xBB\xBF\x00..\x20");
    }

    public function resolveCurrentAccessTokenForRoom(Room $room): ?RoomAccessToken
    {
        return RoomAccessToken::query()
            ->with(['room', 'booking'])
            ->where('room_id', $room->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->whereHas('booking', function ($query) {
                $query->whereIn('status', ['active', 'checked_in']);
            })
            ->latest('id')
            ->first();
    }
}
