<?php

namespace App\Http\Controllers;

use App\Services\Licensing\LicenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Public licence activation endpoint.
 *
 * The desktop app posts a purchased key together with its machine id and gets
 * back a signed licence file it can verify offline. Rate-limited because the
 * key space is guessable in principle; a real key is five groups of four.
 *
 * `transfer: true` means the app found itself on a different PC and the operator
 * chose to move the licence; it is the customer's self-service replacement-PC
 * path and is limited per licence.
 */
class LicenceController extends Controller
{
    public function activate(Request $request, LicenceService $licences): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64'],
            'machine_id' => ['required', 'string', 'max:128'],
            'transfer' => ['sometimes', 'boolean'],
        ]);

        try {
            $licence = $licences->activate(
                $data['key'],
                $data['machine_id'],
                (bool) ($data['transfer'] ?? false),
                $request->ip(),
                $request->userAgent() !== null ? substr($request->userAgent(), 0, 255) : null,
            );

            $token = $licences->signedToken($licence);
        } catch (RuntimeException $e) {
            // Signing is not configured (or broken) on this deployment: say so
            // rather than handing the client an unverifiable licence.
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'token' => $token,
            'property' => $licence->property_name,
            'expires_at' => $licence->expires_at?->toIso8601String(),
            'transfers_remaining' => $licence->transfersRemaining(),
        ]);
    }
}
