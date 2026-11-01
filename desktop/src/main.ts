import { invoke } from "@tauri-apps/api/core";
import { listen } from "@tauri-apps/api/event";
import { openUrl } from "@tauri-apps/plugin-opener";
import { relaunch } from "@tauri-apps/plugin-process";
import { check, type Update } from "@tauri-apps/plugin-updater";
import QRCode from "qrcode";

// The three shapes an installation can take (spec 9.2). An explicit answer —
// never a guess from configuration or hostname — decides what this PC becomes.
type Role = "server" | "terminal" | "staff_client";

interface StepView {
  id: string;
  title: string;
}

interface StepStatusView {
  running: boolean;
  serverActive: boolean;
  completed: string[];
}

interface StepEvent {
  id: string;
  title: string;
  status: "started" | "succeeded" | "skipped" | "failed";
  message: string | null;
}

interface DoneEvent {
  ok: boolean;
  error?: string;
  port?: number;
  serverUrl?: string;
}

interface DiscoveredServer {
  address: string;
  name: string;
  version: string;
  httpPort: number;
}

interface PairingView {
  addresses: string[];
  uris: string[];
  name: string;
  version: string;
  port: number;
}

interface RoleDef {
  title: string;
  blurb: string;
  formHeading: string;
  submitLabel: string;
}

const ROLE_DEFS: Record<Role, RoleDef> = {
  server: {
    title: "Set up this PC as server",
    blurb: "Runs the database and serves other devices on your network.",
    formHeading: "Server setup",
    submitLabel: "Start server setup",
  },
  terminal: {
    title: "Set up as POS terminal",
    blurb: "Works offline and syncs with your server.",
    formHeading: "Terminal setup",
    submitLabel: "Start terminal setup",
  },
  staff_client: {
    title: "Connect to a server",
    blurb: "No installation; just sign in.",
    formHeading: "Connect to a server",
    submitLabel: "Connect",
  },
};

// Step ids that only belong to one role, used to infer a role mid-run.
const SERVER_STEPS = new Set([
  "unpack_postgres",
  "generate_env",
  "create_admin",
  "bind_network",
  "register_firewall",
  "register_cloud",
  "install_tray",
]);
const TERMINAL_STEPS = new Set(["create_database", "pull_snapshot", "register_node"]);

let currentRole: Role | null = null;
let steps: StepView[] = [];
let completedIds = new Set<string>();
let running = false;
let lastFailure: { step: string; message: string } | null = null;

function el(html: string): HTMLElement {
  const template = document.createElement("template");
  template.innerHTML = html.trim();
  return template.content.firstElementChild as HTMLElement;
}

function inject(root: Element, node: Node): void {
  root.replaceChildren(node);
}

function statusBadge(status: StepEvent["status"] | "pending"): string {
  switch (status) {
    case "started":
      return `<span class="badge badge--pending">running</span>`;
    case "succeeded":
      return `<span class="badge badge--ok">done</span>`;
    case "skipped":
      return `<span class="badge badge--muted">skipped</span>`;
    case "failed":
      return `<span class="badge badge--err">failed</span>`;
    case "pending":
      return `<span class="badge badge--idle">pending</span>`;
  }
}

// --- Screens -----------------------------------------------------------------

function renderRoleSelect(): void {
  const root = document.getElementById("root")!;
  const choices = (Object.keys(ROLE_DEFS) as Role[])
    .map(
      (role) => `
        <button class="role-card" data-role="${role}" type="button">
          <span class="role-card__title">${ROLE_DEFS[role].title}</span>
          <span class="role-card__blurb">${ROLE_DEFS[role].blurb}</span>
        </button>`,
    )
    .join("");

  inject(
    root,
    el(`
      <section class="panel">
        <h2>How will this PC be used?</h2>
        <p class="panel__intro">Pick one — the choice cannot be inferred, and it decides everything that happens next.</p>
        <div class="role-grid">${choices}</div>
      </section>`),
  );

  root.querySelectorAll<HTMLButtonElement>("[data-role]").forEach((button) => {
    button.addEventListener("click", () => renderWizard(button.dataset.role as Role));
  });
}

async function renderWizard(role: Role): Promise<void> {
  currentRole = role;
  steps = await invoke<StepView[]>("setup_plan", { role });
  const status = await invoke<StepStatusView>("setup_status", {});
  completedIds = new Set(status.completed);
  running = status.running;
  lastFailure = null;
  renderWizardDom();
}

function renderWizardDom(): void {
  if (!currentRole) return;
  const def = ROLE_DEFS[currentRole];
  const root = document.getElementById("root")!;

  inject(
    root,
    el(`<section class="panel">
      <button class="link-button" data-back type="button">← Change my choice</button>
      <h2>${def.formHeading}</h2>
      ${formHtml(currentRole)}
      <h3 class="steps-title">Steps</h3>
      <ol class="steps">${stepsHtml()}</ol>
      ${running ? runningNoteHtml() : ""}
    </section>`),
  );

  bindForm();
  root.querySelector<HTMLButtonElement>("[data-back]")?.addEventListener("click", renderRoleSelect);
}

function stepsHtml(): string {
  return steps
    .map((step) => {
      const done = completedIds.has(step.id);
      const badge = done
        ? statusBadge("succeeded")
        : `<span class="badge badge--idle">pending</span>`;
      return `<li class="step" data-step="${step.id}">
        <span class="step__title">${step.title}</span>
        <span class="step__badge">${badge}</span>
        <span class="step__message" hidden></span>
      </li>`;
    })
    .join("");
}

function formHtml(role: Role): string {
  const def = ROLE_DEFS[role];
  const submit = `<button class="btn" type="submit"${running ? " data-disabled" : ""}>${def.submitLabel}</button>`;
  let fields = "";
  switch (role) {
    case "server":
      fields = `
        <div class="field">
          <label for="admin-name">Administrator name</label>
          <input id="admin-name" name="adminName" type="text" autocomplete="name" required />
        </div>
        <div class="field">
          <label for="admin-email">Login email</label>
          <input id="admin-email" name="adminEmail" type="email" autocomplete="email" required />
        </div>
        <div class="field">
          <label for="admin-password">Initial password</label>
          <input id="admin-password" name="adminPassword" type="password" autocomplete="new-password" required />
        </div>
        <div class="field">
          <label for="property-name">Property name <span class="optional">(optional)</span></label>
          <input id="property-name" name="propertyName" type="text" />
        </div>`;
      break;
    case "terminal":
      fields = `
        <div class="field">
          <label for="property-code">Property code</label>
          <input id="property-code" name="propertyCode" type="text" autocomplete="off" required />
        </div>
        <div class="field">
          <label for="pairing-code">Pairing code</label>
          <input id="pairing-code" name="pairingCode" type="text" autocomplete="off" required />
        </div>
        <div class="field">
          <label>Server <span class="optional">(pair with your LAN server)</span></label>
          <input name="serverUrl" type="text" placeholder="Auto-detect, or type its address" />
          <button class="btn btn--ghost" type="button" data-find>Find my server</button>
          <div data-results hidden></div>
        </div>`;
      break;
    case "staff_client":
      fields = `
        <div class="field">
          <label for="server-url">Server address</label>
          <div class="field-row">
            <input id="server-url" name="serverUrl" type="url" placeholder="http://192.168.1.20:8000" required />
            <button class="btn btn--ghost" type="button" data-find>Find my server</button>
          </div>
          <div data-results hidden></div>
        </div>
        <p class="hint">Broadcast is often blocked; if discovery finds nothing, type the address the server host shows you.</p>`;
      break;
  }
  return `<form>${fields}${submit}</form>`;
}

function runningNoteHtml(): string {
  return `<p class="note">${lastFailure ? "Something failed below; you can retry once you fix it." : "Setup is running — it may take a few minutes on a fresh machine."}</p>`;
}

// --- Running setup -----------------------------------------------------------

function bindForm(): void {
  const root = document.getElementById("root")!;
  const form = root.querySelector<HTMLFormElement>("form");
  if (!form) return;

  const submit = root.querySelector<HTMLButtonElement>('button[type="submit"]');
  const disable = () => {
    submit?.setAttribute("data-disabled", "true");
    form.setAttribute("data-running", "true");
  };

  bindFinder(form);

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (running || submit?.hasAttribute("data-disabled")) return;

    const data = new FormData(form);
    const value = (name: string) => (data.get(name) as string | null)?.trim() ?? "";

    const payload: Record<string, unknown> = {};
    let command: string;
    switch (currentRole) {
      case "server": {
        command = "setup_run_server";
        payload.adminName = value("adminName");
        payload.adminEmail = value("adminEmail");
        payload.adminPassword = value("adminPassword");
        payload.propertyName = value("propertyName") || null;
        break;
      }
      case "terminal": {
        command = "setup_run_terminal";
        payload.propertyCode = value("propertyCode");
        payload.pairingCode = value("pairingCode");
        payload.serverUrl = value("serverUrl") || null;
        break;
      }
      case "staff_client": {
        command = "setup_run_staff_client";
        payload.serverUrl = value("serverUrl");
        break;
      }
      default:
        return;
    }

    running = true;
    lastFailure = null;
    steps.forEach((step) => completedIds.delete(step.id));
    disable();
    renderStepStates();

    try {
      // Tauri deserializes command arguments BY PARAMETER NAME, and every setup
      // command declares a single `input: ...Input` argument. Passing the fields
      // flat is rejected with "missing required key input" before any setup
      // work runs, so the wrapper object is load-bearing.
      await invoke(command, { input: payload });
    } catch (error) {
      running = false;
      // `renderWizard` is async and replaces `#root`, so the rejection has to be
      // surfaced AFTER it resolves — and on the section, not the form: the note
      // is a sibling of the form, never a descendant.
      await renderWizard(currentRole);
      const section = document
        .getElementById("root")
        ?.querySelector<HTMLElement>("section.panel");
      section?.appendChild(
        el(
          `<p class="note">Setup could not start: ${escapeHtml(String(error))}</p>`,
        ),
      );
    }
  });
}

// Broadcast the discovery probe and offer the found servers. Selecting one
// fills the form's server address — the manual field remains the fallback.
async function bindFinder(form: HTMLFormElement): Promise<void> {
  const button = form.querySelector<HTMLButtonElement>("[data-find]");
  const results = form.querySelector<HTMLElement>("[data-results]");
  const input = form.querySelector<HTMLInputElement>('input[name="serverUrl"]');
  if (!button || !results) return;

  button.addEventListener("click", async () => {
    button.disabled = true;
    button.textContent = "Searching…";
    results.hidden = false;

    try {
      const servers = await invoke<DiscoveredServer[]>("discovery_probe", {});
      if (servers.length === 0) {
        results.replaceChildren(
          el(
            `<p class="hint">No server answered. If it is running, type its address above or ask the host for the pairing code.</p>`,
          ),
        );
        return;
      }

      results.replaceChildren();
      for (const server of servers) {
        const host = server.address.slice(0, server.address.lastIndexOf(":"));
        const url = `http://${host}:${server.httpPort}`;
        const result = el(
          `<button class="finder-result" type="button">
            <span class="finder-result__name">${escapeHtml(server.name)}</span>
            <span class="finder-result__meta">${escapeHtml(url)} · v${escapeHtml(server.version)}</span>
          </button>`,
        );
        result.addEventListener("click", () => {
          if (input) input.value = url;
          results.hidden = true;
        });
        results.appendChild(result);
      }
    } catch (error) {
      results.replaceChildren(
        el(`<p class="hint">Discovery failed: ${escapeHtml(String(error))}</p>`),
      );
    } finally {
      button.disabled = false;
      button.textContent = "Find my server";
    }
  });
}

// Refresh the step rows against the live model without rebuilding the form.
function renderStepStates(): void {
  const root = document.getElementById("root")!;
  for (const step of steps) {
    const row = root.querySelector<HTMLElement>(`[data-step="${step.id}"]`);
    if (!row) continue;
    const done = completedIds.has(step.id);
    const badge = row.querySelector<HTMLElement>(".step__badge");
    if (badge) badge.innerHTML = done ? statusBadge("succeeded") : statusBadge("pending");
  }
}

function setStep(stepId: string, status: StepEvent["status"], message: string | null): void {
  const root = document.getElementById("root")!;
  const row = root.querySelector<HTMLElement>(`[data-step="${stepId}"]`);
  if (!row) return;

  const badge = row.querySelector<HTMLElement>(".step__badge");
  if (badge) badge.innerHTML = statusBadge(status);

  const messageEl = row.querySelector<HTMLElement>(".step__message");
  if (messageEl) {
    messageEl.textContent =
      status === "failed" && message ? `Cause: ${message}` : message ?? "";
    messageEl.hidden = !message;
    messageEl.className =
      "step__message " + (status === "failed" ? "step__message--err" : "");
  }

  if (status === "failed") {
    lastFailure = { step: stepId, message: message ?? "unknown cause" };
  }
}

// --- Events from the engine --------------------------------------------------

function applyStep(event: StepEvent): void {
  setStep(event.id, event.status, event.message);
  if (event.status === "succeeded") {
    completedIds.add(event.id);
  }
}

function finish(done: DoneEvent): void {
  running = false;
  if (!currentRole) return;

  if (done.ok) {
    renderDone(done);
  } else {
    const message = lastFailure
      ? `Step “${lastFailure.step}” failed. ${lastFailure.message}`
      : done.error ?? "Setup failed for an unknown reason.";

    const panel = el(`<section class="panel panel--err">
      <h2>Setup did not complete</h2>
      <p>${escapeHtml(message)}</p>
      <p class="hint">You can retry — already-completed steps are not repeated.</p>
      <button class="btn" type="button" data-retry>Try again</button>
    </section>`);
    panel
      .querySelector<HTMLButtonElement>("[data-retry]")
      ?.addEventListener("click", () => renderWizard(currentRole!));
    inject(document.getElementById("root")!, panel);
  }
}

function renderDone(done: DoneEvent): void {
  const root = document.getElementById("root")!;
  const target = launchTarget(done);
  const panel = el(`<section class="panel panel--ok">
    <h2>${doneHeading()}</h2>
    <p>${doneBody(done)}</p>
    ${target ? `<button class="btn" type="button" data-open>Open the application</button>` : ""}
  </section>`);
  inject(root, panel);

  root.querySelector<HTMLButtonElement>("[data-open]")?.addEventListener("click", async () => {
    if (target) {
      try {
        await openUrl(target);
      } catch {
        window.location.href = target;
      }
    }
  });

  if (currentRole === "server") {
    void appendServerPairing(panel);
  }
}

// Render the pairing fallbacks on the server: a QR code per LAN address, plus
// the plain pairing URIs a person can read out loud or paste.
async function appendServerPairing(panel: HTMLElement): Promise<void> {
  let pairing: PairingView;
  try {
    pairing = await invoke<PairingView>("discovery_pairing", {});
  } catch {
    return;
  }

  const section = el(`<div class="pairing">
    <h3>Pair other devices</h3>
    <p class="hint">Show a code or share an address. A terminal or staff client scans or pastes it to reach this server when broadcast is blocked.</p>
    <div class="pairing__list"></div>
  </div>`);
  panel.appendChild(section);
  const list = section.querySelector<HTMLElement>(".pairing__list")!;

  pairing.uris.forEach((uri) => {
    const canvas = document.createElement("canvas");
    canvas.width = 150;
    canvas.height = 150;
    const text = document.createElement("code");
    text.textContent = uri;
    const row = document.createElement("div");
    row.className = "pairing__row";
    row.append(canvas, text);
    list.appendChild(row);
    void QRCode.toCanvas(canvas, uri, { width: 150, margin: 1 }).catch(() => {});
  });
}

function launchTarget(done: DoneEvent): string | null {
  switch (currentRole) {
    case "server":
      return `http://127.0.0.1:${done.port ?? 8000}`;
    case "staff_client":
      return done.serverUrl ?? null;
    default:
      return null;
  }
}

function doneHeading(): string {
  switch (currentRole) {
    case "server":
      return "Your server is ready";
    case "terminal":
      return "Terminal is ready";
    case "staff_client":
      return "Connected";
    default:
      return "Setup is ready";
  }
}

function doneBody(done: DoneEvent): string {
  switch (currentRole) {
    case "server":
      return `The database is migrated, seeded, and the administrator account is created. The server is listening on your network and other devices can connect to it.`;
    case "terminal":
      return `The local database is created and migrated. Offline selling and the initial snapshot will arrive in the next phase.`;
    case "staff_client":
      return `This PC points at ${escapeHtml(done.serverUrl ?? "")}. You can open the application and sign in.`;
    default:
      return "";
  }
}

function escapeHtml(value: string): string {
  const div = document.createElement("div");
  div.textContent = value;
  return div.innerHTML;
}

// --- Auto-update -------------------------------------------------------------
//
// The updater manifest is signed with the release key and verified before an
// installer runs (`plugins.updater.pubkey` in tauri.conf.json). The banner is
// deliberately explicit: a hotel server restarting is a visible event, so the
// operator chooses when, and the app stops Postgres/FrankenPHP first so the
// installer can replace the bundled runtime instead of failing on locked files.

function updateBanner(): HTMLElement | null {
  return document.querySelector<HTMLElement>("[data-update-banner]");
}

function showUpdateBanner(update: Update): void {
  if (updateBanner()) return;

  const banner = el(`<div class="update-banner" data-update-banner>
    <div class="update-banner__text">
      <strong>Update available — version ${escapeHtml(update.version)}</strong>
      <span data-update-status>Install it now, or keep working and update later.</span>
    </div>
    <div class="update-banner__actions">
      <button class="btn btn--ghost" type="button" data-update-later>Later</button>
      <button class="btn" type="button" data-update-install>Install update</button>
    </div>
  </div>`);

  document.body.appendChild(banner);

  const status = banner.querySelector<HTMLElement>("[data-update-status]")!;
  const install = banner.querySelector<HTMLButtonElement>("[data-update-install]")!;
  const later = banner.querySelector<HTMLButtonElement>("[data-update-later]")!;

  later.addEventListener("click", () => banner.remove());

  install.addEventListener("click", async () => {
    install.disabled = true;
    later.disabled = true;
    status.textContent = "Stopping the local server…";

    try {
      // Release the database and HTTP runtime: the new build replaces the
      // bundled Postgres/PHP binaries, which are locked while they run.
      await invoke("setup_stop_server");
      status.textContent = "Downloading the update…";

      let downloaded = 0;
      let total = 0;

      await update.downloadAndInstall((event) => {
        if (event.event === "Started") {
          total = event.data.contentLength ?? 0;
        } else if (event.event === "Progress") {
          downloaded += event.data.chunkLength;
          status.textContent = total
            ? `Downloading the update… ${Math.round((downloaded / total) * 100)}%`
            : "Downloading the update…";
        } else if (event.event === "Finished") {
          status.textContent = "Installing; the application will restart…";
        }
      });

      await relaunch();
    } catch (error) {
      install.disabled = false;
      later.disabled = false;
      status.textContent = `Update failed: ${String(error)}`;
    }
  });
}

async function initAutoUpdate(): Promise<void> {
  try {
    if (!(await invoke<boolean>("updates_enabled"))) {
      return;
    }

    const update = await check();
    if (update) {
      showUpdateBanner(update);
    }
  } catch (error) {
    // Offline machines and a missing manifest must never block the wizard.
    console.warn("update check failed", error);
  }
}

// --- Startup ----------------------------------------------------------------

async function boot(): Promise<void> {
  await listen<StepEvent>("setup://step", (event) => applyStep(event.payload));
  await listen<DoneEvent>("setup://done", (event) => finish(event.payload));

  const status = await invoke<StepStatusView>("setup_status", {});

  if (status.running) {
    const role = inferRole(status.completed);
    if (role) {
      await renderWizard(role);
      void initAutoUpdate();
      return;
    }
  }
  renderRoleSelect();
  void initAutoUpdate();
}

function inferRole(completed: string[]): Role | null {
  if (completed.some((id) => SERVER_STEPS.has(id))) return "server";
  if (completed.some((id) => TERMINAL_STEPS.has(id))) return "terminal";
  return null;
}

window.addEventListener("DOMContentLoaded", () => void boot());