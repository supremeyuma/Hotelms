<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Sync\SyncNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Exchange a single-use pairing code for a node credential.
 *
 * This endpoint is necessarily pre-credential: the caller holds nothing to
 * authenticate with until the exchange succeeds, so it lives outside the
 * sync.node middleware group and carries its own strict throttle (see
 * routes/api.php) to keep guessing a pairing code expensive.
 *
 * The credential is returned once and never again: only its SHA-256 hash is
 * stored - the same convention the sync.node middleware compares against
 * (AuthenticateSyncNode::handle), so an issued credential authenticates and a
 * database leak does not yield usable node credentials.
 */
class NodeRegistrationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // The real schema has no property-code column (properties carries
            // id/name/location/amenities), so property_code carries the
            // property id the pairing code must have been minted for.
            'property_code' => ['required', 'integer', 'exists:properties,id'],
            'pairing_code' => ['required', 'string', 'max:64'],
            'node_name' => ['required', 'string', 'max:120'],
            'node_type' => ['required', 'in:lan_server,terminal'],
        ]);

        return DB::transaction(function () use ($validated): JsonResponse {
            $pairing = DB::table('sync_pairing_codes')
                ->where('code', $validated['pairing_code'])
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($pairing === null
                || (int) $pairing->property_id !== (int) $validated['property_code']) {
                return $this->rejected();
            }

            // Single use, consumed atomically: whoever flips used_at first
            // wins. A concurrent redemption blocks on this row, then matches
            // nothing - 0 rows affected means another request already spent
            // the code, so this one must not mint a node for it.
            $consumed = DB::table('sync_pairing_codes')
                ->where('id', $pairing->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            if ($consumed === 0) {
                return $this->rejected();
            }

            $secret = Str::random(64);

            $node = SyncNode::create([
                'node_id' => (string) Str::ulid(),
                'name' => $validated['node_name'],
                'type' => $validated['node_type'],
                'status' => 'active',
                'property_id' => $pairing->property_id,
                'credential' => hash('sha256', $secret),
            ]);

            // Shown exactly once: the plaintext exists only in this response.
            return response()->json([
                'node_id' => $node->node_id,
                'credential' => $secret,
                'cloud_url' => config('app.url'),
            ], 201);
        });
    }

    /**
     * One answer for every rejection path so an invalid, expired, spent or
     * wrong-property code is indistinguishable to the caller.
     */
    private function rejected(): JsonResponse
    {
        return response()->json(['message' => 'Invalid or expired pairing code.'], 422);
    }
}
