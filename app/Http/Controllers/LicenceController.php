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
 */
class LicenceController extends Controller
{
    public function activate(Request $request, LicenceService $licences): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64'],
            'machine_id' => ['required', 'string', 'max:128'],
        ]);

        try {
            $licence = $licences->activate($data['key'], $data['machine_id']);
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
        ]);
    }
}
