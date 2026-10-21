// State isolation for the native desktop suite.
//
// `boot()` in the wizard resumes a persisted role when `setup-state.json` still
// says `running`, and there is **no `reset` IPC command** (the `invoke_handler`
// registers exactly nine commands, none of them `reset`). So every run has to
// start from a clean app-data directory — otherwise a previous interrupted
// `npm run e2e` makes the next one resume mid-setup and the specs assert against
// the wrong screen.
//
// WHY WE MUST NOT WIPE THE WHOLE DIRECTORY:
// `%LOCALAPPDATA%\com.moorelife.desktop` is also where WebView2 keeps its
// browser profile (`EBWebView/`, hundreds of files). Deleting that destroys the
// developer's real profile — cookies, cache, local storage, saved state — and
// forces WebView2 to rebuild it, which is slow and can change app behaviour in
// ways unrelated to the test. So we delete only the wizard's own generated
// artifacts and leave `EBWebView/` (and anything we do not recognise) alone.

import { existsSync, rmSync } from "node:fs";
import { join } from "node:path";

/**
 * The Tauri app-data directory for this app.
 *
 * Derived from `productName`/`identifier` in `tauri.conf.json`
 * (`identifier = "com.moorelife.desktop"`). Tauri maps the identifier straight
 * onto the OS "local app data" location, which on Windows is `%LOCALAPPDATA%`.
 * We read `LOCALAPPDATA` from the environment rather than hardcoding the
 * username, so this works on any checkout/runner.
 *
 * NOTE: Local, not Roaming. (`%APPDATA%` would be wrong.)
 */
export function appDataDir(): string {
  const localAppData = process.env.LOCALAPPDATA;
  if (!localAppData) {
    throw new Error(
      "LOCALAPPDATA is not set — appdata.ts can only locate the app data " +
        "directory on Windows.",
    );
  }
  return join(localAppData, "com.moorelife.desktop");
}

/**
 * The wizard-owned artifacts inside {@link appDataDir}, verified from source as
 * living directly in that directory. This is an allowlist on purpose: anything
 * not listed (notably `EBWebView/`) is preserved.
 */
export const MANAGED_ARTIFACTS: readonly string[] = [
  "setup-state.json", // the resume hazard — boot() resumes a `running` role from it
  ".env", // generated app config
  "identity.env", // node identity/key material
  "hotelms.sqlite", // app SQLite database
  "hotelms.sqlite-journal", // ...and its SQLite sidecars
  "hotelms.sqlite-wal",
  "hotelms.sqlite-shm",
  "admin.json", // admin bootstrap record
  "pgdata", // bundled Postgres cluster
  "postgres", // trimmed Postgres runtime
  "logs", // frankenphp.log, postgres.log
] as const;

/**
 * Delete only the wizard's generated artifacts from the app-data directory,
 * leaving `EBWebView/` intact. Returns the list of paths that actually existed
 * and were removed, so callers can log what changed.
 *
 * Safe to call when the directory does not exist yet (first run).
 */
export function resetAppData(): string[] {
  const dir = appDataDir();
  const removed: string[] = [];

  if (!existsSync(dir)) {
    return removed;
  }

  for (const name of MANAGED_ARTIFACTS) {
    const target = join(dir, name);
    if (!existsSync(target)) {
      continue;
    }
    rmSync(target, { recursive: true, force: true });
    removed.push(target);
  }

  return removed;
}
