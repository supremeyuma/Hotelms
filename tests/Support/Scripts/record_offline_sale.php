<?php

/*
 * Stand-alone subprocess used by MidShiftRestartTest to prove a sale survives
 * a process boundary.
 *
 * The parent test process migrates the file-backed database and seeds the
 * fixtures, then exits its connection. This script boots a *fresh* PHP and
 * Laravel against that same SQLite file, writes one complete offline sale, and
 * prints the resulting docket identity for the parent to assert on.
 *
 * A transaction that only looks atomic inside one process is not atomic; this
 * is the second process that must see the committed sale, or not see a
 * half-written one.
 */

use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\User;
use App\Services\ClubPosService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$options = getopt('', ['db::', 'node-id::', 'fixtures::']);

$dbPath = $options['db'] ?? null;
$nodeId = $options['node-id'] ?? 'node-a';
$fixturesPath = $options['fixtures'] ?? null;

if (! $dbPath || ! $fixturesPath || ! is_file($dbPath) || ! is_file($fixturesPath)) {
    fwrite(STDERR, 'usage: php record_offline_sale.php --db <file> --node-id <id> --fixtures <json>'.PHP_EOL);
    exit(1);
}

if (! is_readable($dbPath)) {
    fwrite(STDERR, "database file is not readable: {$dbPath}".PHP_EOL);
    exit(1);
}

// Same guard the test suite enforces: a cached config would point this process
// at the wrong database and silently poison the proof.
if (file_exists(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
    fwrite(STDERR, 'Refusing to run with cached config.'.PHP_EOL);
    exit(2);
}

// These are already set by the time Dotenv runs, so the loaded .env(.testing)
// cannot override them: this process must use exactly the parent's file.
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$dbPath);

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// The sale must be captured for sync. The peer points at a port that refuses
// a connection instantly, so the connectivity probe in completeSale() fails
// fast without any network: the write itself needs no peer, this is the whole
// point of the offline path. Delivery cursors are irrelevant here; the parent
// asserts on them afterwards.
config([
    'sync.enabled' => true,
    'sync.node_id' => $nodeId,
    'sync.node_type' => 'terminal',
    'sync.peers' => ['http://127.0.0.1:1'],
    'sync.credential' => 'secret',
]);

try {
    $fixtures = json_decode((string) file_get_contents($fixturesPath), true);

    $device = PosDevice::findOrFail($fixtures['device_id']);
    $session = PosSession::findOrFail($fixtures['session_id']);
    $staff = User::findOrFail($fixtures['staff_id']);
    $item = MenuItem::findOrFail($fixtures['item_id']);

    $pos = $app->make(ClubPosService::class);

    $docket = $pos->openDocket($session, $staff);
    $pos->addItem($docket, $item, 2, $staff);
    $ack = $pos->completeSale($docket, [['method' => 'cash', 'amount' => 2000]], $staff);

    fwrite(STDOUT, json_encode([
        'docket_id' => $ack['docket']->id,
        'sync_uid' => $ack['docket']->sync_uid,
        'status' => $ack['docket']->status,
    ]));

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n".$e->getTraceAsString().PHP_EOL);
    fwrite(STDERR, 'resolved_default='.config('database.default').PHP_EOL);
    fwrite(STDERR, 'resolved_db='.var_export(config('database.connections.'.config('database.default').'.database', null), true).PHP_EOL);
    exit(1);
}
