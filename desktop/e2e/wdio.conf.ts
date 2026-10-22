// Natively-driven desktop e2e suite (the release gate — see
// docs/superpowers/plans/2026-10-21-production-walkthrough-test.md).
//
// This drives the *built Tauri binary* through the embedded WebDriver provider.
// It boots a real Postgres cluster and binds real ports, so it must never run
// in parallel.
//
// IMPORTANT: the binary must be built with the `e2e` Cargo feature, or it will
// not contain the embedded WebDriver server and the session will time out:
//
//   cargo build --features e2e --manifest-path desktop/src-tauri/Cargo.toml
//
// The release build (no `e2e`) deliberately carries no WebDriver server.

import { type ChildProcess, spawn } from "node:child_process";
import { existsSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

// Resolve everything from this config file's own location so the suite works on
// any checkout (no hardcoded drive paths). File lives at
// <repo>/desktop/e2e/wdio.conf.ts, so the repo root is two levels up.
const here = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(here, "..", "..");

// The Cargo package is named `desktop`, so the binary is `desktop.exe`.
const appBinaryPath = resolve(
  repoRoot,
  "desktop",
  "src-tauri",
  "target",
  "debug",
  "desktop.exe",
);

if (!existsSync(appBinaryPath)) {
  console.warn(
    `[wdio] app binary not found at ${appBinaryPath}\n` +
      "       build it first: " +
      "cargo build --features e2e --manifest-path desktop/src-tauri/Cargo.toml",
  );
}

// The debug binary is a Tauri *dev* build: it loads `devUrl`
// (http://localhost:1420) at startup rather than the embedded `dist/` assets,
// because `tauri.conf.json` sets `build.devUrl` and debug builds use it. Without
// the Vite dev server the WebView renders a connection-error page and every
// selector fails. So the harness owns the dev server's lifecycle: start it
// before the session, stop it after. (A release build with `--features e2e`
// would embed the assets and not need this — see the docs task.)
const devServerUrl = "http://localhost:1420";
let devServer: ChildProcess | undefined;

async function waitForDevServer(timeoutMs = 60000): Promise<void> {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    try {
      const res = await fetch(devServerUrl, { method: "GET" });
      if (res.ok) {
        return;
      }
    } catch {
      // not up yet
    }
    await new Promise((r) => setTimeout(r, 500));
  }
  throw new Error(`Vite dev server did not become ready at ${devServerUrl}`);
}

export const config = {
  runner: "local",

  specs: ["./specs/**/*.spec.ts"],

  // One instance only. This drives a single window against a real Postgres
  // cluster and real ports; parallelism would collide on all of them.
  maxInstances: 1,

  services: [
    [
      "tauri", // short service name in native mode (browser mode uses the full package name)
      {
        appBinaryPath,
        driverProvider: "embedded",
      },
    ],
  ],

  capabilities: [
    {
      browserName: "tauri",
      "tauri:options": {
        // Must point at the same binary the service spawns.
        application: appBinaryPath,
      },
    },
  ],

  framework: "mocha",

  // Real initdb + migrations take minutes, not seconds.
  mochaOpts: {
    ui: "bdd",
    timeout: 600000,
  },

  reporters: ["spec"],

  logLevel: "info",
  waitforTimeout: 30000,
  connectionRetryTimeout: 120000,
  connectionRetryCount: 1,

  // Start the Vite dev server the debug binary loads, and stop it afterwards.
  async onPrepare() {
    // --- SAFETY GUARD: a cached Laravel config would redirect the wizard's
    // migrations to the developer's MySQL database. -----------------------
    //
    // In development the desktop app's `app_root` falls back to the REPOSITORY
    // root (see `resolve_paths` in `desktop/src-tauri/src/lib.rs`), so the
    // wizard runs `php artisan migrate --force` and `php artisan db:seed
    // --force` inside this repo. The setup passes the role's database settings
    // as PROCESS environment variables and relies on Laravel's Dotenv being
    // immutable, so the process environment normally wins over the repo's
    // `.env` (which points at the dev MySQL `hotelms` database).
    //
    // That reasoning holds ONLY while `bootstrap/cache/config.php` is absent.
    // With a cached config, Laravel uses the cached array verbatim and ignores
    // the environment entirely — the wizard would then migrate and seed the
    // REAL dev MySQL database. This was reproduced directly: with a config
    // cache in place, `DB_CONNECTION=pgsql` still resolved to `mysql`/3306.
    //
    // So refuse to run rather than risk it. Clearing the cache is the
    // developer's call, not the suite's: `config:cache` bakes their local
    // environment into the app, and silently deleting it would change their
    // dev setup.
    const cachedConfig = resolve(repoRoot, "bootstrap", "cache", "config.php");
    if (existsSync(cachedConfig)) {
      throw new Error(
        "[wdio] Refusing to start: a cached Laravel config exists at\n" +
          `       ${cachedConfig}\n` +
          "       The desktop setup runs `php artisan migrate --force` and\n" +
          "       `db:seed --force` against the repository root. With a cached\n" +
          "       config Laravel ignores the process environment and would\n" +
          "       migrate and seed the DEV MySQL database `hotelms`.\n" +
          "       Fix: run `php artisan config:clear` and retry.",
      );
    }

    // Spawn Vite directly through the current Node binary rather than via
    // `npm run dev`. With npm we would get the shell's pid back and killing it
    // orphans Vite (Windows), leaving port 1420 bound for the next run. Spawning
    // the vite entry point means `devServer.pid` *is* the server, so stopping it
    // is exact.
    const viteBin = resolve(
      repoRoot,
      "desktop",
      "node_modules",
      "vite",
      "bin",
      "vite.js",
    );
    devServer = spawn(process.execPath, [viteBin], {
      cwd: resolve(repoRoot, "desktop"),
      // Do not inherit/pipe the child's stdio: an inherited pipe keeps the
      // launcher's streams open and can hang the run after the specs finish.
      stdio: "ignore",
    });
    await waitForDevServer();
  },

  async onComplete() {
    // `devServer.pid` is the Vite process itself (see onPrepare), so a direct
    // kill is exact — no orphaned grandchild and no leftover port 1420 binding.
    if (devServer?.pid && !devServer.killed) {
      devServer.kill();
      devServer = undefined;
    }
  },
};
