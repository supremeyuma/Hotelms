# Sync Convergence

How the offline node-to-node sync engine provably brings independent nodes to
the same state, and how to reproduce that proof.

This document covers Phase 4R (gaps G20-G25). The terminal scope and the
per-table merge rules live in [SYNC_SCOPE.md](SYNC_SCOPE.md); the transport,
endpoints and cycle described here are the delivery half.

## What "converged" means

Two nodes are converged when, for every synced table, the business contents of
the rows match. `id`, `sync_hlc` and `sync_origin_node_id` are excluded from the
comparison:

- `id` is each node's own auto-increment and diverges the moment two nodes
  write independently - which is the entire reason `sync_uid` exists.
- `sync_hlc` and `sync_origin_node_id` name the node that last wrote the row,
  so they differ by construction.

Everything else - business columns, `sync_uid`, `sync_deleted_at` and both
timestamps - must match. That is the equality the tests assert.

## The cycle

A cycle runs in two directions against every configured peer, and each
direction has its own cursor row in `sync_cursors`:

| Direction | Column value | Meaning |
|-----------|--------------|---------|
| Inbound   | `direction = 'in'`  | How far this node has applied changes **from** the peer |
| Outbound  | `direction = 'out'` | How far this node has pushed its own log **to** the peer |

The cursors are keyed by `(node_id, target_node_id, direction)`, so a peer is
tracked by its node id, not its address: repointing a peer at a new address
keeps its delivery history.

**Push** sends only `id > outbound cursor`, and the outbound cursor advances
only after the peer confirms the batch. A failed push is retried, never
silently dropped.

**Pull** pages the peer's log with `since = inbound cursor`. Each page is
applied inside a single database transaction that also advances the inbound
cursor, so a page is either fully applied and marked done, or fully rolled back
and retried - never half applied.

A dead peer is caught, logged and skipped; the cycle continues against the
remaining peers. This is the spec section 4.3 requirement that a terminal
always has somewhere to sync to.

## Topology: full mesh, because nothing relays

`SyncApplier` applies a remote change inside `SyncLogger::applyingRemote()`,
which suppresses capture. An applied change is **not** re-logged as a local
change. This is deliberate - it is what stops a change from bouncing forever
around the mesh - but it has a consequence:

> A change reaches exactly the nodes that are directly peered with its origin.
> No node forwards another node's change.

A star (every terminal peers with the cloud) therefore does **not** converge.
Node A's row reaches the cloud, but node B pulling from the cloud only receives
the cloud's *own* changes, not A's, because the cloud never re-logged them.

For every pair of nodes to converge, every pair must be peered directly. Peering
is bidirectional (each peer both pushes to and pulls from the other), so a
single edge between two nodes is enough to move both nodes' own changes across
it. With three nodes that means a triangle: A peers B and C, B peers A and C,
C peers A and B.

Deployments with terminals that cannot be dialled are a known limit; see
"Known limits" below.

## Reproducing the proof

### In CI: `ThreeNodeConvergenceTest`

```
php artisan test --filter=ThreeNodeConvergenceTest
```

This boots three genuinely separate file-backed SQLite databases (two `:memory:`
connections would silently share one handle). Only the network socket is
stubbed: `Http::fake` intercepts each outbound call and drives the peer's real
`ChangeLogController` against that peer's real database, so the transport's
batching/headers/JSON handling, the controller's transaction, the applier and
the cursors all actually run.

It proves three things:

1. **Convergence.** One row written on each isolated node, then two rounds of
   cycles, and all three `inventory_items` tables match.
2. **A dead peer does not block the rest.** With one peer unreachable, the
   cycle reports it in `failed` and still reconciles against the live peer.
3. **A failed page rolls back with its cursor.** A page carrying a valid change
   and an unappliable one leaves neither the row nor an advanced inbound
   cursor behind, so the page is retried rather than skipped half-applied.

### Against real processes: `scripts/three-node-sync-demo.sh`

```
scripts/three-node-sync-demo.sh
```

Needs bash and a POSIX host (Linux/macOS, or WSL on Windows). It boots three
real `php artisan serve` processes on loopback (`node-a` terminal, `node-b` LAN,
`node-c` cloud), each with its own SQLite file and identity, in the same full
mesh. It writes a row on each while isolated, reconciles, compares the three
tables, then stops the LAN server, confirms the terminal still syncs to the
cloud, restarts it and reconciles again. Exits non-zero if any comparison
fails.

## Operational notes

Enable sync with:

```
SYNC_ENABLED=true
SYNC_NODE_ID=<stable ULID, minted once>
SYNC_NODE_TYPE=terminal|lan|cloud
SYNC_CREDENTIAL=<shared secret; peers store only its SHA-256>
SYNC_PEERS={"node-b":{"base_url":"http://10.0.0.2:8000"}}
```

- Sync is **off by default**: an unconfigured deployment behaves exactly as it
  did before sync existed.
- `SYNC_NODE_ID` must be stable. Changing it orphans every row the node has
  written on its peers and breaks the last-write-wins tie-break.
- Peers are ordered by preference; a terminal lists the LAN server first and the
  cloud second so a dead LAN server degrades throughput, not reconciliation.
- `sync:run`, `sync:push`, `sync:pull` and `sync:status` drive and inspect a
  node by hand. The scheduled `SyncWorker` runs a full cycle every minute.

## Known limits

- **No relay.** Changes do not transit through an intermediate node. Every pair
  that must converge has to be peered.
- **Undialable terminals.** A terminal behind NAT that only pushes cannot have
  other nodes pull from it, and its changes will not reach a third node. Such a
  terminal must push directly to every node it needs to reach, or a relay must
  be added (a future phase).
- **Delete versus edit.** A delete and a concurrent edit of the same row are a
  recorded conflict, not an automatic merge (G53-G55, tracked separately).
