// Reaches the artefacts the desktop **terminal** role produces, plus the
// pairing data the LAN server needs to mint for it.
//
// A terminal is not driven by configuration alone: it exchanges a single-use
// code for a credential at POST /api/sync/register. That code has no admin UI
// (operator-only by design), so the spec mints one the same way an operator
// would — through `sync:pairing-code`'s table — and then runs the real wizard.
// The assertions read the resulting SQLite file and the server's registry.

import { execFileSync } from "node:child_process";
import { existsSync, readFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { randomBytes } from "node:crypto";

import { appDataPath } from "./server";
import { psql, psqlCount } from "./server";

const here = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(here, "..", "..", "..");

/** The terminal's local database, written by the terminal role. */
export function terminalDatabasePath(): string {
  return appDataPath("hotelms.sqlite");
}

/** The environment the last completed role wrote (server or terminal). */
export function readAppEnv(key: string): string | null {
  const file = appDataPath(".env");
  if (!existsSync(file)) {
    return null;
  }
  for (const line of readFileSync(file, "utf8").split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith("#")) continue;
    const eq = trimmed.indexOf("=");
    if (eq > 0 && trimmed.slice(0, eq).trim() === key) {
      return trimmed.slice(eq + 1).trim();
    }
  }
  return null;
}

/**
 * Mint a single-use pairing code bound to `propertyId`, exactly as the
 * operator-only `sync:pairing-code` command does.
 *
 * Inserted through psql rather than `artisan` so it needs no PHP process and
 * no second copy of the server's environment.
 */
export function mintPairingCode(propertyId: number): string {
  const code = randomBytes(9)
    .toString("base64")
    .replace(/[^A-Za-z0-9]/g, "")
    .toUpperCase()
    .slice(0, 12)
    .padEnd(12, "0");

  psql(
    `INSERT INTO sync_pairing_codes (property_id, code, expires_at, created_at, updated_at) ` +
      `VALUES (${propertyId}, '${code}', now() + interval '15 minutes', now(), now());`,
  );

  return code;
}

/** How many nodes the server has registered. */
export function registeredNodeCount(): number {
  return psqlCount("SELECT count(*) FROM sync_nodes;");
}

/** The node a terminal registered, by the property it was paired for. */
export function registeredNode(propertyId: number): {
  name: string;
  type: string;
  credential_present: boolean;
} | null {
  const row = psql(
    `SELECT name, type, coalesce(nullif(credential, ''), '') <> '' ` +
      `FROM sync_nodes WHERE property_id = ${propertyId} ORDER BY id DESC LIMIT 1;`,
  );
  if (row === "") {
    return null;
  }
  const [name, type, credential] = row.split("|");
  return {
    name,
    type,
    credential_present: credential === "t",
  };
}

/**
 * Run one read-only query against the terminal's SQLite file and return the
 * scalar.
 *
 * The app ships both PHP and pdo_sqlite, so the query runs through the same
 * runtime the terminal itself uses — no extra tooling on the machine. The SQL
 * is written into a throwaway script outside the repository.
 */
export function sqliteScalar(sql: string): string {
  const php = join(
    repoRoot,
    "resources",
    "bin",
    "x86_64-pc-windows-msvc",
    "php.exe",
  );
  if (!existsSync(php)) {
    throw new Error(`bundled php.exe not found at ${php}`);
  }

  // `php -r` takes code without an opening tag; including one is a parse error.
  const script = [
    `$pdo = new PDO('sqlite:' . $argv[1]);`,
    `$stmt = $pdo->query($argv[2]);`,
    `echo $stmt === false ? '' : (string) $stmt->fetchColumn();`,
  ].join("\n");

  const output = execFileSync(
    php,
    ["-r", script, terminalDatabasePath(), sql],
    {
      encoding: "utf8",
      timeout: 60000,
      env: {
        ...process.env,
        PHPRC: dirname(php),
      },
    },
  );

  return output.trim();
}
