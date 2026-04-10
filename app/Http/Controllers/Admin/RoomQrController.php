<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\AuditLoggerService as AuditLogger;
use App\Services\RoomGuestAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoomQrController extends Controller
{
    public function __construct(
        protected RoomGuestAccessService $roomGuestAccessService,
        protected AuditLogger $auditLogger
    ) {
        $this->middleware(['auth', 'role:manager|md']);
    }

    public function show(Room $room): Response
    {
        abort_if(blank($room->qr_key), 404, 'QR code has not been generated for this room.');

        $svg = $this->roomGuestAccessService->generateQrSvg($room);
        $filename = $this->fileName($room) . '.svg';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function download(Room $room): Response
    {
        abort_if(blank($room->qr_key), 404, 'QR code has not been generated for this room.');

        $svg = $this->roomGuestAccessService->generateQrSvg($room);
        $filename = $this->fileName($room) . '.svg';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function generate(Request $request, Room $room)
    {
        $forceRegenerate = (bool) ($request->route('regenerate') ?? $request->boolean('regenerate'));
        $hadQr = filled($room->qr_key);

        $room = $this->roomGuestAccessService->ensureRoomQr($room, $forceRegenerate);

        $this->auditLogger->log(
            $hadQr && $forceRegenerate ? 'room_qr_regenerated' : 'room_qr_generated',
            'Room',
            $room->id,
            ['qr_key' => $room->qr_key]
        );

        return back()->with(
            'success',
            $hadQr && $forceRegenerate
                ? "A new QR code has been issued for {$room->name}."
                : "QR code is ready for {$room->name}."
        );
    }

    public function invalidate(Room $room)
    {
        if (blank($room->qr_key)) {
            return back()->with('success', "QR code is already inactive for {$room->name}.");
        }

        $this->roomGuestAccessService->invalidateRoomQr($room);

        $this->auditLogger->log('room_qr_invalidated', 'Room', $room->id);

        return back()->with('success', "QR code has been invalidated for {$room->name}.");
    }

    protected function fileName(Room $room): string
    {
        $identifier = $room->code ?: $room->name ?: 'room-' . $room->id;

        return 'room-qr-' . str($identifier)->slug();
    }
}
