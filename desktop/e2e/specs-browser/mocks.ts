/**
 * Mocked Tauri IPC for the desktop wizard, running in plain Chrome
 * (WebdriverIO browser mode).
 *
 * WHY A PRELOAD SCRIPT INSTEAD OF `browser.tauri.mock()`
 * ---------------------------------------------------
 * `desktop/src/main.ts` boots on `DOMContentLoaded` and immediately calls
 * `invoke("setup_status")`. The tauri service installs its IPC interceptor as a
 * BiDi *preload* script, so the interceptor really is in place before the app's
 * first `invoke()` — but `window.__wdio_mocks__` starts out empty, and the
 * interceptor rejects any command that has no mock:
 *
 *     Error: unmocked Tauri command in browser mode: setup_status
 *
 * Mocks registered from a test body (`browser.tauri.mock(...)`) only live in
 * the page they were registered in: every navigation opens a fresh realm whose
 * `window.__wdio_mocks__` starts out empty again. "Navigate, register the
 * mocks, reload" therefore does NOT work — the reload wipes the mocks and the
 * app boots unmocked.
 *
 * The fix is to install the mocks from our *own* preload script, registered
 * after the service's, so every navigation starts with the mocks already in
 * place — before the app's first `invoke()`.
 *
 * The mocks are created with the service's own factory
 * (`window.__wdio_spy__.fn`, installed by the service's preload), so they
 * behave exactly like the ones the service registers.
 */

/** Where the Vite dev server serves the desktop frontend. */
export const APP_URL = "http://localhost:1420";

export interface StepView {
  id: string;
  title: string;
}

export interface PairingView {
  addresses: string[];
  uris: string[];
  name: string;
  version: string;
  port: number;
}

export interface WizardMockOptions {
  /** Setup steps the engine reports as already completed. */
  completed?: string[];
  /** Whether a setup run is currently in progress. */
  running?: boolean;
  /** Whether the local server is already active. */
  serverActive?: boolean;
  /** The step list `setup_plan` returns. */
  plan?: StepView[];
  /** The pairing information `discovery_pairing` returns. */
  pairing?: PairingView;
}

export const DEFAULT_PLAN: StepView[] = [
  { id: "unpack_postgres", title: "Unpack Postgres" },
  { id: "generate_env", title: "Generate environment" },
  { id: "migrate", title: "Run migrations" },
];

export const DEFAULT_PAIRING: PairingView = {
  addresses: ["192.168.1.10:8000"],
  uris: ["moorelife://pair?addr=192.168.1.10%3A8000"],
  name: "Test Server",
  version: "0.1.0",
  port: 8000,
};

/** The commands the wizard invokes that have no answer of their own. */
const SUBMIT_COMMANDS = [
  "setup_run_server",
  "setup_run_terminal",
  "setup_run_staff_client",
];

/** Builds the preload script that installs the wizard mocks on every page load. */
function buildPreloadScript(options: Required<WizardMockOptions>): string {
  const values = {
    setup_status: {
      running: options.running,
      serverActive: options.serverActive,
      completed: options.completed,
    },
    setup_plan: options.plan,
    discovery_probe: [],
    discovery_pairing: options.pairing,
  };

  return `() => {
  var values = ${JSON.stringify(values)};
  var spy = window.__wdio_spy__;
  if (!spy || !spy.fn) {
    throw new Error('@wdio/native-spy is not available in the page');
  }
  if (!window.__wdio_mocks__) { window.__wdio_mocks__ = {}; }
  function register(command, value) {
    var fn = spy.fn();
    fn.mockName('tauri.' + command);
    fn.mockResolvedValue(value);
    window.__wdio_mocks__[command] = fn;
  }
  Object.keys(values).forEach(function (command) {
    register(command, values[command]);
  });
  // The submit commands are only called long after boot, but they still need a
  // mock: an unmocked invoke rejects and the wizard shows an error instead of
  // the double-submit guard doing its job.
  ${JSON.stringify(SUBMIT_COMMANDS)}.forEach(function (command) {
    if (window.__wdio_mocks__[command]) { return; }
    register(command, undefined);
  });
}`;
}

/**
 * Installs the wizard mocks as a BiDi preload script.
 *
 * The script is registered *after* the service's own preload, so it runs after
 * it on every navigation. A previously registered preload is removed first —
 * there must only ever be one, or the last registered would win.
 */
async function registerWizardMocks(
  browser: WebdriverIO.Browser,
  options: WizardMockOptions = {},
): Promise<void> {
  const resolved: Required<WizardMockOptions> = {
    completed: options.completed ?? [],
    running: options.running ?? false,
    serverActive: options.serverActive ?? false,
    plan: options.plan ?? DEFAULT_PLAN,
    pairing: options.pairing ?? DEFAULT_PAIRING,
  };

  await removeWizardMocks(browser);

  const { script } = await browser.scriptAddPreloadScript({
    functionDeclaration: buildPreloadScript(resolved),
  });

  (
    browser as WebdriverIO.Browser & { __wdioMockPreload__?: string }
  ).__wdioMockPreload__ = script;
}

/** Removes the preload script installed by {@link registerWizardMocks}. */
async function removeWizardMocks(
  browser: WebdriverIO.Browser,
): Promise<void> {
  const target = browser as WebdriverIO.Browser & {
    __wdioMockPreload__?: string;
  };
  if (!target.__wdioMockPreload__) {
    return;
  }
  await browser.scriptRemovePreloadScript({ script: target.__wdioMockPreload__ });
  target.__wdioMockPreload__ = undefined;
}

/**
 * Opens the wizard frontend with the mocked IPC in place.
 *
 * Order matters: the preload is registered *before* the navigation, because the
 * preload is what makes the mocks exist while `main.ts` boots.
 */
export async function openWizardPage(
  browser: WebdriverIO.Browser,
  options: WizardMockOptions = {},
): Promise<void> {
  await registerWizardMocks(browser, options);
  await browser.url(APP_URL);
}
