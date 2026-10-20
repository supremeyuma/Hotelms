<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Sync\ReferenceSnapshot;
use Illuminate\Http\JsonResponse;

/**
 * Serve the reference snapshot to an authenticated peer node.
 *
 * The payload carries users.password hashes. That is inherent to a terminal
 * authenticating against its own copy of the staff accounts, and is why the
 * route sits behind the sync.node middleware like every other node-to-node
 * endpoint — the hashes are only ever readable by a node that already holds
 * a valid credential.
 */
class ReferenceSnapshotController extends Controller
{
    public function __construct(private readonly ReferenceSnapshot $snapshot) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->snapshot->build());
    }
}
