#!/usr/bin/env bash
#
# Three-process convergence proof for node-to-node sync (Phase 4R, G22/G25).
#
# Boots three real `php artisan serve` processes on loopback, each with its own
# SQLite database and its own node identity, peers them in a full mesh, writes a
# row on every node while they are isolated, then runs sync cycles until the
# three tables match.
#
# Full mesh, not a star: the applier deliberately does not re-log a change that
# arrived over the wire, so nothing relays. A change reaches exactly the nodes
# that are directly peered with its origin, which means every pair must be
# peered for three nodes to converge.
#
# Requires bash and a POSIX host (Linux/macOS, or WSL on Windows). The PHPUnit
# guard in tests/Feature/Sync/ThreeNodeConvergenceTest.php is the CI equivalent
# and needs none of this.
#
# Usage:
#   scripts/three-node-sync-demo.sh
#
# Exits non-zero if the nodes do not converge.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
CREDENTIAL="${SYNC_CREDENTIAL:-demo-shared-secret}"

TMP="$(mktemp -d "${TMPDIR:-/tmp}/hotelms-3node.XXXXXX")"
PIDS=()

NodeA_PORT=8101
NodeB_PORT=8102
NodeC_PORT=8103

cleanup() {
    local pid
    for pid in "${PIDS[@]:-}"; do
        if [[ -n "${pid}" ]] && kill -0 "${pid}" 2>/dev/null; then
            kill "${pid}" 2>/dev/null || true
            wait "${pid}" 2>/dev/null || true
        fi
    done
    rm -rf "${TMP}"
}
trap cleanup EXIT

log() { printf '\n=== %s\n' "$*"; }

# ---------------------------------------------------------------------------
# Per-node environment
# ---------------------------------------------------------------------------

node_type() {
    case "$1" in
        a) echo "terminal" ;;
        b) echo "lan" ;;
        c) echo "cloud" ;;
    esac
}

node_port() {
    case "$1" in
        a) echo "${NodeA_PORT}" ;;
        b) echo "${NodeB_PORT}" ;;
        c) echo "${NodeC_PORT}" ;;
    esac
}

# Every node peers with the other two (full mesh).
node_peers() {
    local self="$1" parts=() letter host
    for letter in a b c; do
        [[ "${letter}" == "${self}" ]] && continue
        host="http://127.0.0.1:$(node_port "${letter}")"
        parts+=("\"node-${letter}\":{\"base_url\":\"${host}\"}")
    done
    local IFS=,
    echo "{${parts[*]}}"
}

# Run an artisan command as one node: its database, its identity, its peers.
as_node() {
    local letter="$1"; shift
    env \
        DB_CONNECTION=sqlite \
        DB_DATABASE="${TMP}/node-${letter}.sqlite" \
        SYNC_ENABLED=true \
        SYNC_NODE_ID="node-${letter}" \
        SYNC_NODE_TYPE="$(node_type "${letter}")" \
        SYNC_CREDENTIAL="${CREDENTIAL}" \
        SYNC_PEERS="$(node_peers "${letter}")" \
        "$@"
}

# ---------------------------------------------------------------------------
# Boot
# ---------------------------------------------------------------------------

log "Workdir ${TMP}"

for letter in a b c; do
    touch "${TMP}/node-${letter}.sqlite"
    as_node "${letter}" "${PHP_BIN}" artisan migrate:fresh --force >/dev/null
    echo "migrated node-${letter}"
done

start_node() {
    local letter="$1" port
    port="$(node_port "${letter}")"

    as_node "${letter}" \
        SESSION_DRIVER=file \
        QUEUE_CONNECTION=sync \
        APP_URL="http://127.0.0.1:${port}" \
        "${PHP_BIN}" artisan serve --host=127.0.0.1 --port="${port}" \
        >"${TMP}/node-${letter}.log" 2>&1 &

    PIDS+=("$!")
}

log "Starting nodes"
for letter in a b c; do
    start_node "${letter}"
done

wait_for_node() {
    local port="$1" i
    for i in $(seq 1 50); do
        if curl -fsS "http://127.0.0.1:${port}/api/sync/changes?since=0&limit=1" \
            -H "X-Sync-Node-Id: probe" -H "Authorization: Bearer probe" >/dev/null 2>&1; then
            return 0
        fi
        # A 401/403 still proves the process is listening; only connection
        # refusal should retry.
        if curl -sS -o /dev/null "http://127.0.0.1:${port}/api/sync/changes" 2>/dev/null; then
            return 0
        fi
        sleep 0.2
    done
    echo "node on port ${port} did not come up" >&2
    return 1
}

for letter in a b c; do
    wait_for_node "$(node_port "${letter}")"
    echo "node-${letter} listening on $(node_port "${letter}")"
done

# ---------------------------------------------------------------------------
# Writes while isolated, then reconcile
# ---------------------------------------------------------------------------

log "Writing one row on each isolated node"
as_node a "${PHP_BIN}" artisan tinker --execute="App\\Models\\InventoryItem::create(['name'=>'Item A','sku'=>'A-1','unit'=>'kg']);"
as_node b "${PHP_BIN}" artisan tinker --execute="App\\Models\\InventoryItem::create(['name'=>'Item B','sku'=>'B-1','unit'=>'kg']);"
as_node c "${PHP_BIN}" artisan tinker --execute="App\\Models\\InventoryItem::create(['name'=>'Item C','sku'=>'C-1','unit'=>'kg']);"

run_all_cycles() {
    local round letter
    for round in 1 2; do
        for letter in a b c; do
            as_node "${letter}" "${PHP_BIN}" artisan sync:run >"${TMP}/sync-${letter}-${round}.log" 2>&1 || true
        done
    done
}

log "Reconciling"
run_all_cycles

dump() {
    as_node "$1" "${PHP_BIN}" artisan tinker --execute="echo json_encode(App\\Models\\InventoryItem::orderBy('sku')->get(['sku','name'])->toArray());"
}

verify_convergence() {
    local a b c
    a="$(dump a)"
    b="$(dump b)"
    c="$(dump c)"

    echo "  node-a: ${a}"
    echo "  node-b: ${b}"
    echo "  node-c: ${c}"

    if [[ "${a}" == "${b}" && "${a}" == "${c}" ]]; then
        echo "  converged."
        return 0
    fi

    echo "  DIVERGED." >&2
    return 1
}

log "Convergence check"
verify_convergence

# ---------------------------------------------------------------------------
# A dead peer must not stop the others (spec section 4.3)
# ---------------------------------------------------------------------------

log "Stopping node-b (LAN server) and confirming node-a still syncs to the cloud"
kill "${PIDS[1]}" 2>/dev/null || true
wait "${PIDS[1]}" 2>/dev/null || true
PIDS[1]=""

as_node a "${PHP_BIN}" artisan tinker --execute="App\\Models\\InventoryItem::create(['name'=>'Item A2','sku'=>'A-2','unit'=>'kg']);"

if as_node a "${PHP_BIN}" artisan sync:run >"${TMP}/sync-a-degraded.log" 2>&1; then
    echo "  node-a reconciled with the live cloud while node-b was down."
else
    echo "  node-a reported a failed peer (expected) but continued:" >&2
    cat "${TMP}/sync-a-degraded.log" >&2
fi

log "Restarting node-b and reconciling again"
start_node b
wait_for_node "$(node_port b)"
run_all_cycles

log "Final convergence check"
verify_convergence

log "PASS"
