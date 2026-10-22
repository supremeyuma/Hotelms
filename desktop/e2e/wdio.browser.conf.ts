// Fast loop only — browser mode with mocked IPC (no Rust build, no Postgres).
// A green fast loop NEVER substitutes for the native release gate (`npm run e2e`).
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

import { APP_URL } from "./specs-browser/mocks";

const here = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(here, "..", "..");

export const config = {
  runner: "local",

  specs: ["./specs-browser/**/*.spec.ts"],

  // One instance only: every spec shares the same Vite dev server and the
  // same mocked IPC preload.
  maxInstances: 1,

  services: [
    [
      // Browser mode is only reachable through the *full* package name. The
      // short name ("tauri") resolves to the native driver path.
      "@wdio/tauri-service",
      {
        mode: "browser",
        // Required in browser mode: the service navigates the plain Chrome
        // session to this URL before the specs run.
        devServerUrl: APP_URL,
        // The service owns the dev server's lifecycle (waits for readiness,
        // tears it down afterwards). `devServerUrl` is still required: a
        // `devServer` *object* cannot report its own URL back to the service.
        devServer: {
          command: "npm run dev",
          cwd: resolve(repoRoot, "desktop"),
          reuseExistingServer: true,
        },
      },
    ],
  ],

  capabilities: [{ browserName: "tauri" }],

  framework: "mocha",
  mochaOpts: { timeout: 20000 },

  reporters: ["spec"],

  logLevel: "info",

  /**
   * WebdriverIO v10 compatibility shim.
   *
   * `@wdio/tauri-service@1.5.0` still calls `browser.overwriteCommand(name, fn, boolean)`,
   * which v10 rejects outright ("Passing a boolean as the third argument to
   * `overwriteCommand` was removed in WebdriverIO v10"). The service's `before()`
   * therefore aborts in the middle of `initBrowserMode()` — after the IPC
   * interceptor is installed, but before the post-navigation re-injection and
   * the mock auto-sync are registered.
   *
   * Translating the boolean into the v10 options object lets the service finish
   * initialising. This runs before the service's own `before()` hook, because
   * WebdriverIO appends service hooks after the ones declared in the config.
   */
  before(_capabilities, _specs, browser) {
    const overwrite = browser.overwriteCommand.bind(browser);
    browser.overwriteCommand = (name, fn, options) =>
      overwrite(
        name,
        fn,
        typeof options === "boolean" ? { attachToElement: options } : options,
      );
  },
};
