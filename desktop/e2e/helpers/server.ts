// Reaches the artefacts the desktop **server** role produces on the machine.
//
// The wizard's own UI is not evidence: it reports what it believes it did.
// These helpers read the real embedded Postgres cluster, the real FrankenPHP
// log and the real Windows autostart registry, so a spec can assert on what
// actually changed on the disk and in the OS.
//
// Everything here is Windows-only, because the desktop app this suite drives
// is a Windows application (see helpers/appdata.ts for the same constraint).

import { execFileSync } from "node:child_process";
import { existsSync, readFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

import { appDataDir } from "./appdata";

const here = dirname(fileURLToPath(import.meta.url));
// desktop/e2e/helpers -> desktop/e2e -> desktop -> <repo>
const repoRoot = resolve(here, "..", "..", "..");

// --- The cluster the wizard creates ------------------------------------------
// Mirrors desktop/src-tauri/src/postgres.rs (LOCAL_DB_*), which owns these
// values. They are duplicated here rather than imported because the Rust side
// is not reachable from the test process.

export const DB_HOST = "127.0.0.1";
export const DB_PORT = 5432;
export const DB_NAME = "hotelms";
export const DB_USER = "hotelms";
export const DB_PASSWORD = "hotelms";

/** The port the server role serves the application on. */
export const HTTP_PORT = 8000;

// --- Paths -------------------------------------------------------------------

export function appDataPath(...parts: string[]): string {
  return join(appDataDir(), ...parts);
}

/** The Postgres data directory the wizard creates. */
export function pgDataDir(): string {
  return appDataPath("pgdata");
}

/** The `PG_VERSION` file that only a real `initdb` writes. */
export function pgVersionFile(): string {
  return join(pgDataDir(), "PG_VERSION");
}

export function frankenphpLogPath(): string {
  return appDataPath("logs", "frankenphp.log");
}

export function postgresLogPath(): string {
  return appDataPath("logs", "postgres.log");
}

/** The Laravel application's own log — the app runs from the repo in dev. */
export function laravelLogPath(): string {
  return join(repoRoot, "storage", "logs", "laravel.log");
}

// --- Files -------------------------------------------------------------------

/** The last `lines` lines of `path`, or a note that it does not exist. */
export function tailFile(path: string, lines = 40): string {
  if (!existsSync(path)) {
    return `(no file at ${path})`;
  }
  const all = readFileSync(path, "utf8").split(/\r?\n/);
  return all.slice(Math.max(0, all.length - lines)).join("\n");
}

/**
 * The runtime logs a failure needs: what FrankenPHP said, what Postgres said
 * and what Laravel said. Without these a failed wizard is just a red dot.
 */
export function logTails(lines = 40): string {
  return [
    `--- ${frankenphpLogPath()} (tail) ---`,
    tailFile(frankenphpLogPath(), lines),
    `--- ${postgresLogPath()} (tail) ---`,
    tailFile(postgresLogPath(), lines),
    `--- ${laravelLogPath()} (tail) ---`,
    tailFile(laravelLogPath(), lines),
  ].join("\n");
}

// --- psql --------------------------------------------------------------------

/**
 * The `psql.exe` binaries that can query the cluster, best first.
 *
 * First: the copy the wizard unpacked into the app-data directory, because that
 * is the binary that belongs to the cluster being inspected. Second: the
 * vendored runtime in the repository, which is present before the cluster is
 * ever created. A candidate that exists but cannot run (a trimmed runtime
 * without its DLLs) is skipped at query time rather than trusted.
 */
export function psqlCandidates(): string[] {
  return [
    appDataPath("postgres", "bin", "psql.exe"),
    join(
      repoRoot,
      "resources",
      "postgres-runtime",
      "x86_64-pc-windows-msvc",
      "pgsql",
      "bin",
      "psql.exe",
    ),
  ];
}

/**
 * The `psql.exe` to query the cluster with.
 *
 * Preferred: the copy the wizard unpacked into the app-data directory, because
 * that is the binary that belongs to the cluster being inspected. Fallback:
 * the vendored runtime in the repository, used when the cluster does not exist
 * yet.
 */
export function resolvePsql(): string {
  const candidates = psqlCandidates();
  const found = candidates.find((candidate) => existsSync(candidate));
  if (!found) {
    throw new Error(
      "no psql.exe found — looked in:\n  " + candidates.join("\n  "),
    );
  }
  return found;
}

/**
 * Run one SQL statement against the wizard's cluster and return its output.
 *
 * `-t -A` gives tuples-only, unaligned output, so a scalar comes back as the
 * bare value instead of a padded table.
 */
export function psql(sql: string, database = DB_NAME): string {
  const failures: string[] = [];
  for (const binary of psqlCandidates()) {
    if (!existsSync(binary)) {
      failures.push(`${binary}: not present`);
      continue;
    }
    try {
      const output = execFileSync(
        binary,
        [
          "-h",
          DB_HOST,
          "-p",
          String(DB_PORT),
          "-U",
          DB_USER,
          "-d",
          database,
          "-v",
          "ON_ERROR_STOP=1",
          "-t",
          "-A",
          "-c",
          sql,
        ],
        {
          encoding: "utf8",
          env: { ...process.env, PGPASSWORD: DB_PASSWORD },
          timeout: 60000,
        },
      );
      return output.trim();
    } catch (error) {
      failures.push(`${binary}: ${(error as Error).message}`);
    }
  }
  throw new Error(
    `psql could not run the query with any candidate:\n  ${failures.join("\n  ")}`,
  );
}

/** A single integer from the cluster. Throws when the query returns nothing. */
export function psqlCount(sql: string, database = DB_NAME): number {
  const raw = psql(sql, database);
  const value = Number(raw.trim());
  if (!Number.isInteger(value)) {
    throw new Error(`expected a number from \`${sql}\`, got ${JSON.stringify(raw)}`);
  }
  return value;
}

// --- Processes and ports -----------------------------------------------------

function powershell(script: string): string {
  return execFileSync(
    "powershell",
    ["-NoProfile", "-NonInteractive", "-Command", script],
    { encoding: "utf8", timeout: 60000 },
  );
}

/** The PIDs listening on `port`. Empty when nothing holds it. */
export function listenersOn(port: number): number[] {
  const output = powershell(
    `(Get-NetTCPConnection -LocalPort ${port} -State Listen -ErrorAction SilentlyContinue ` +
      `| Select-Object -ExpandProperty OwningProcess -Unique) -join ','`,
  ).trim();
  if (!output) {
    return [];
  }
  return output
    .split(",")
    .map((part) => Number(part.trim()))
    .filter((pid) => Number.isInteger(pid) && pid > 0);
}

/**
 * Stop the servers a previous run left behind.
 *
 * The wizard's server is two child processes: an embedded Postgres whose
 * binary lives inside the app-data directory, and FrankenPHP holding the HTTP
 * port. Killing the app binary does not kill them (Windows has no process
 * groups), so a later run would find port 5432 taken and fail in a way that
 * has nothing to do with the code under test.
 *
 * Only those two are touched. A `php.exe` that happens to sit on the same
 * port — the developer's own `artisan serve` — is never killed.
 */
export function stopLeftoverServers(): string[] {
  const dataBin = appDataPath("postgres", "bin").replace(/'/g, "''");
  const script = `
$ErrorActionPreference = 'SilentlyContinue'
$stopped = @()
Get-CimInstance Win32_Process | Where-Object {
  $_.Name -eq 'postgres.exe' -and $_.ExecutablePath -like '${dataBin}\\*'
} | ForEach-Object {
  Stop-Process -Id $_.ProcessId -Force
  $stopped += ('postgres.exe (pid ' + $_.ProcessId + ')')
}
$owners = Get-NetTCPConnection -LocalPort ${HTTP_PORT} -State Listen -ErrorAction SilentlyContinue |
  Select-Object -ExpandProperty OwningProcess -Unique
foreach ($owner in $owners) {
  $proc = Get-CimInstance Win32_Process -Filter "ProcessId = $owner"
  if ($proc -and $proc.Name -eq 'frankenphp.exe') {
    Stop-Process -Id $owner -Force
    $stopped += ('frankenphp.exe (pid ' + $owner + ')')
  }
}
$stopped
`;
  const output = powershell(script).trim();
  return output ? output.split(/\r?\n/).filter((line) => line.trim() !== "") : [];
}

// --- The autostart registry entry -------------------------------------------
//
// The `install_tray` step registers the executable under the current user's
// Run key. That is a real change to the machine, so the spec reads the value
// before the run and puts it back afterwards.

const AUTOSTART_KEY = "HKCU\\Software\\Microsoft\\Windows\\CurrentVersion\\Run";
const AUTOSTART_VALUE = "MooreLife";

/** The current autostart command, or null when there is none. */
export function readAutostart(): string | null {
  try {
    const output = execFileSync(
      "reg",
      ["query", AUTOSTART_KEY, "/v", AUTOSTART_VALUE],
      { encoding: "utf8", timeout: 30000 },
    );
    const match = output.match(/REG_SZ\s+(.*)$/m);
    return match ? match[1].trim() : null;
  } catch {
    return null;
  }
}

export function writeAutostart(value: string): void {
  execFileSync(
    "reg",
    [
      "add",
      AUTOSTART_KEY,
      "/v",
      AUTOSTART_VALUE,
      "/t",
      "REG_SZ",
      "/d",
      value,
      "/f",
    ],
    { stdio: "ignore", timeout: 30000 },
  );
}

export function deleteAutostart(): void {
  try {
    execFileSync(
      "reg",
      ["delete", AUTOSTART_KEY, "/v", AUTOSTART_VALUE, "/f"],
      { stdio: "ignore", timeout: 30000 },
    );
  } catch {
    // Already gone — nothing to undo.
  }
}
