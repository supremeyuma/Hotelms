/**
 * Read-only access to the e2e SQLite database.
 *
 * Specs assert on the records a workflow produced, not only on the page it
 * rendered — a green screen with no row behind it is exactly the failure mode
 * that matters before a release. The file is queried through PHP's pdo_sqlite,
 * so no extra tooling is needed and the server that owns it is never disturbed.
 */
import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const REPO_ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..', '..');
const DATABASE = resolve(REPO_ROOT, 'database', 'e2e.sqlite');

/** One value from the e2e database. */
export function sqliteScalar(sql: string): string {
    const script = [
        "$pdo = new PDO('sqlite:' . $argv[1]);",
        '$stmt = $pdo->query($argv[2]);',
        "echo $stmt === false ? '' : (string) $stmt->fetchColumn();",
    ].join('\n');

    const output = execFileSync('php', ['-r', script, DATABASE, sql], {
        encoding: 'utf8',
        timeout: 60_000,
    });

    return output.trim();
}

/** One integer from the e2e database. */
export function sqliteCount(sql: string): number {
    const raw = sqliteScalar(sql);
    const value = Number(raw);
    if (!Number.isInteger(value)) {
        throw new Error(`expected a number from \`${sql}\`, got ${JSON.stringify(raw)}`);
    }
    return value;
}
