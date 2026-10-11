<?php

namespace App\Http\Middleware;

use App\Models\Sync\SyncNode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate a node-to-node sync request.
 *
 * A node presents its own node id and its secret. The receiver stores only the
 * SHA-256 hash of that secret, so the plaintext is never kept server-side.
 */
class AuthenticateSyncNode
{
    public function handle(Request $request, Closure $next): Response
    {
        $nodeId = $request->header('X-Sync-Node-Id');
        $token = $request->bearerToken();

        if (! $nodeId || ! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $node = SyncNode::where('node_id', $nodeId)->first();

        if ($node === null || $node->credential === null
            || ! hash_equals($node->credential, hash('sha256', $token))) {
            return response()->json(['message' => 'Invalid node credentials.'], 401);
        }

        if ($node->status !== 'active') {
            return response()->json(['message' => 'Node is not active.'], 403);
        }

        $node->forceFill(['last_seen_at' => now()])->saveQuietly();

        $request->attributes->set('sync_node', $node);

        return $next($request);
    }
}
