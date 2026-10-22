// End-to-end proof that the desktop **server** role really boots embedded
// Postgres + FrankenPHP and serves a working Laravel application.
//
// The wizard's UI is not evidence. This spec records the real step events
// (which vanish when `renderDone()` replaces `#root`), inspects real artefacts
// on disk and in Postgres, probes the live HTTP server (`/up` and the login
// flow), and restores the machine-scoped autostart registry entry it flips.
//
// The run takes minutes (initdb + 177 migrations + seed). See
// docs/superpowers/plans/2026-10-21-production-walkthrough-test.md Task 3.

import { execFileSync } from "node:child_process";
import { existsSync } from "node:fs";
import { join } from "node:path";

import { resetAppData, appDataDir } from "../helpers/appdata";
import {
  DB_HOST,
  DB_PORT,
  DB_NAME,
  DB_USER,
  DB_PASSWORD,
  HTTP_PORT,
  appDataPath,
  pgVersionFile,
  frankenphpLogPath,
  postgresLogPath,
  laravelLogPath,
  tailFile,
  logTails,
  psql,
  psqlCount,
  listenersOn,
  stopLeftoverServers,
  readAutostart,
  writeAutostart,
  deleteAutostart,
} from "../helpers/server";

const SERVER_STEPS = [
  "unpack_postgres",
  "generate_env",
  "migrate",
  "seed",
  "create_admin",
  "bind_network",
  "register_firewall",
  "register_cloud",
  "install_tray",
];

type StepBadge = {
  id: string;
  title: string | null;
  status: string | null;
  message: string | null;
};

type RecordedSteps = {
  steps: StepBadge[];
};

describe("desktop wizard — server role boots Postgres and FrankenPHP", function () {
  // initdb + migrations + seed are slow on a fresh app-data dir.
  this.timeout(600000);

  let previousAutostart: string | null = null;
  let stoppedLeftovers: string[] = [];
  const adminEmail = `e2e-server-${Date.now()}@example.test`;
  const adminPassword = "E2e-Server-Passw0rd!";
  const adminName = "E2E Server Admin";
  const propertyName = "E2E Property";

  before(async function () {
    // The harness already refuses to run with bootstrap/cache/config.php present
    // (onPrepare), but be explicit anyway.
    const cachedConfig = join(
      process.cwd(),
      "bootstrap",
      "cache",
      "config.php",
    );
    if (existsSync(cachedConfig)) {
      throw new Error(
        "bootstrap/cache/config.php exists — run `php artisan config:clear` before this test",
      );
    }

    // Capture the machine-scoped autostart value before install_tray changes it.
    previousAutostart = readAutostart();

    // Stop any processes left behind by a previous run (app-data-scoped
    // postgres.exe and frankenphp.exe on 8000). Never touch a developer's
    // php.exe / artisan serve.
    stoppedLeftovers = stopLeftoverServers();

    // Start from a clean wizard state. resetAppData() leaves EBWebView/ alone.
    const removed = resetAppData();
    if (removed.length > 0) {
      console.log(`[server-e2e] resetAppData removed:\n  ${removed.join("\n  ")}`);
    }
    if (stoppedLeftovers.length > 0) {
      console.log(`[server-e2e] stopped leftovers:\n  ${stoppedLeftovers.join("\n  ")}`);
    }

    // The window is created by the builder; refresh so we observe the app.
    await browser.refresh();

    // Record step events/badges before renderDone() replaces #root. The DOM
    // rows vanish on completion, but the Tauri events fire separately and the
    // MutationObserver catches intermediate states; we re-read on every mutation.
    await browser.execute(() => {
      const root = document.getElementById("root") as HTMLElement | null;
      const state = {
        steps: [] as Array<{
          id: string;
          title: string | null;
          status: string | null;
          message: string | null;
        }>,
        seen: new Map<string, number>(),
      };

      function capture() {
        const rows = document.querySelectorAll<HTMLElement>("[data-step]");
        for (const row of rows) {
          const id = row.getAttribute("data-step") || "";
          if (!id) continue;
          const titleEl = row.querySelector<HTMLElement>(".step__title");
          const badgeEl = row.querySelector<HTMLElement>(".step__badge");
          const msgEl = row.querySelector<HTMLElement>(".step__message");
          const badgeText = badgeEl?.textContent?.trim() || null;
          // Normalise the badge class/status into a stable token.
          let status: string | null = null;
          if (badgeText) {
            const lower = badgeText.toLowerCase();
            if (lower === "done") status = "done";
            else if (lower === "failed") status = "failed";
            else if (lower === "skipped") status = "skipped";
            else if (lower === "running") status = "running";
            else if (lower === "pending") status = "pending";
            else status = lower;
          }
          const message = msgEl?.textContent?.trim() || null;
          const rec = {
            id,
            title: titleEl?.textContent?.trim() || null,
            status,
            message,
          };
          // Keep the latest observation for this step id.
          const idx = state.seen.get(id);
          if (idx === undefined) {
            state.seen.set(id, state.steps.length);
            state.steps.push(rec);
          } else {
            state.steps[idx] = rec;
          }
        }
      }

      // @ts-expect-error - test hook
      (window as any).__e2e = state;

      const obs = new MutationObserver(() => capture());
      obs.observe(document.documentElement, {
        childList: true,
        subtree: true,
        characterData: true,
        attributes: true,
      });
      capture();

      // Also listen to Tauri events if available (withGlobalTauri: true).
      const tauri = (window as any).__TAURI__;
      if (tauri?.event?.listen) {
        tauri.event.listen("setup://step", (ev: any) => {
          const p = ev?.payload || {};
          const id = p.id || "";
          const status =
            p.status === "succeeded"
              ? "done"
              : p.status === "started"
              ? "running"
              : p.status === "skipped"
              ? "skipped"
              : p.status === "failed"
              ? "failed"
              : p.status || null;
          const rec = {
            id,
            title: p.title || null,
            status,
            message: p.message ?? null,
          };
          const idx = state.seen.get(id);
          if (idx === undefined) {
            state.seen.set(id, state.steps.length);
            state.steps.push(rec);
          } else {
            state.steps[idx] = { ...state.steps[idx], ...rec };
          }
        });
        tauri.event.listen("setup://done", (_ev: any) => {
          capture();
        });
      }
    });
  });

  after(async function () {
    // Restore the autostart registry entry to its previous state.
    try {
      if (previousAutostart === null) {
        deleteAutostart();
      } else {
        writeAutostart(previousAutostart);
      }
    } catch (e) {
      console.log(`[server-e2e] failed to restore autostart: ${e}`);
    }

    // Stop servers again so the next run can delete pgdata.
    try {
      const stopped = stopLeftoverServers();
      if (stopped.length > 0) {
        console.log(`[server-e2e] after(): stopped leftovers:\n  ${stopped.join("\n  ")}`);
      }
    } catch (e) {
      console.log(`[server-e2e] after() failed to stop servers: ${e}`);
    }
  });

  it("shows the role picker and starts server setup", async function () {
    const heading = await $("//*[normalize-space(text())='How will this PC be used?']");
    await heading.waitForDisplayed({ timeout: 60000 });
    await expect(heading).toBeDisplayed();

    const serverCard = await $('[data-role="server"]');
    await serverCard.waitForDisplayed({ timeout: 30000 });
    await expect(serverCard).toBeDisplayed();

    await serverCard.click();

    // Fill the server form
    await $("#admin-name").waitForDisplayed({ timeout: 30000 });
    await $("#admin-name").setValue(adminName);
    await $("#admin-email").setValue(adminEmail);
    await $("#admin-password").setValue(adminPassword);
    await $("#property-name").setValue(propertyName);

    const submit = await $('button[type="submit"]');
    await submit.waitForClickable({ timeout: 10000 });
    await submit.click();
  });

  it("completes the server setup with all required steps", async function () {
    // Wait for the done panel or a failure panel.
    await browser.waitUntil(
      async () => {
        const openBtn = await $("[data-open]");
        if (await openBtn.isExisting()) return true;
        const retryBtn = await $("[data-retry]");
        if (await retryBtn.isExisting()) return true;
        return false;
      },
      { timeout: 600000, interval: 2000, timeoutMsg: "setup did not finish" },
    );

    const recorded = (await browser.execute(() => {
      // @ts-expect-error - test hook
      return (window as any).__e2e || { steps: [] };
    })) as RecordedSteps;

    const stepMap = new Map<string, StepBadge>();
    for (const s of recorded.steps) {
      stepMap.set(s.id, s);
    }

    // Assert every expected step appears and is terminal.
    for (const id of SERVER_STEPS) {
      const s = stepMap.get(id);
      if (!s) {
        throw new Error(`step "${id}" was never recorded\n${logTails(60)}`);
      }
      if (s.status !== "done" && s.status !== "skipped" && s.status !== "failed") {
        throw new Error(
          `step "${id}" not terminal (status=${s.status}, message=${s.message})\n${logTails(60)}`,
        );
      }
    }

    // MUST succeed steps per requirements
    const mustSucceed = [
      "unpack_postgres",
      "generate_env",
      "migrate",
      "seed",
      "create_admin",
      "bind_network",
      "install_tray",
    ];
    for (const id of mustSucceed) {
      const s = stepMap.get(id);
      if (!s || s.status !== "done") {
        throw new Error(
          `required step "${id}" did not succeed: ${JSON.stringify(s)}\n${logTails(80)}`,
        );
      }
    }

    // register_cloud must have exact message
    const cloud = stepMap.get("register_cloud");
    if (!cloud) {
      throw new Error(`register_cloud step not recorded\n${logTails(60)}`);
    }
    const cloudMsg =
      "Cloud registration is not configured yet; retryable, and local operation is unaffected.";
    if (cloud.status !== "done" || cloud.message !== cloudMsg) {
      throw new Error(
        `register_cloud unexpected: status=${cloud.status}, message=${JSON.stringify(cloud.message)}\n${logTails(80)}`,
      );
    }

    // register_firewall - do NOT assert fixed badge; just terminal + non-empty message
    const firewall = stepMap.get("register_firewall");
    if (!firewall) {
      throw new Error(`register_firewall step not recorded\n${logTails(60)}`);
    }
    if (firewall.status !== "done" && firewall.status !== "skipped" && firewall.status !== "failed") {
      throw new Error(
        `register_firewall not terminal: ${JSON.stringify(firewall)}\n${logTails(60)}`,
      );
    }
    if (!firewall.message || firewall.message.trim() === "") {
      throw new Error(
        `register_firewall message is empty: ${JSON.stringify(firewall)}\n${logTails(60)}`,
      );
    }
    console.log(`[server-e2e] register_firewall: status=${firewall.status}, message=${firewall.message}`);

    // If there was a failure panel, report it with logs
    const retryBtn = await $("[data-retry]");
    if (await retryBtn.isExisting()) {
      const panel = await $(".panel--err");
      const text = (await panel.getText()).catch(() => "");
      throw new Error(`setup failed with panel:\n${text}\n\n${logTails(100)}`);
    }
  });

  it("shows done panel with pairing rows containing moorelife://pair", async function () {
    const openBtn = await $("[data-open]");
    await openBtn.waitForDisplayed({ timeout: 30000 });
    await expect(openBtn).toBeDisplayed();

    // Pairing rows are added asynchronously after discovery_pairing resolves
    await browser.waitUntil(
      async () => {
        const rows = await $$(".pairing__row");
        return rows.length >= 1;
      },
      { timeout: 15000, interval: 500, timeoutMsg: "pairing rows never rendered" },
    );

    const rows = await $$(".pairing__row");
    expect(rows.length).toBeGreaterThanOrEqual(1);
    for (const row of rows) {
      const canvas = await row.$("canvas");
      await expect(canvas).toBeExisting();
      const code = await row.$("code");
      await expect(code).toBeDisplayed();
      const text = await code.getText();
      expect(text).toContain("moorelife://pair");
    }
  });

  it("created pgdata/PG_VERSION", async function () {
    const pgVersion = pgVersionFile();
    // Give it a moment in case of filesystem latency
    await browser.pause(1000);
    if (!existsSync(pgVersion)) {
      throw new Error(`pgdata/PG_VERSION not found at ${pgVersion}\n${logTails(80)}`);
    }
  });

  it("has the hotelms database with migrated tables in the embedded cluster", async function () {
    // Wait for Postgres to be ready
    let ready = false;
    for (let i = 0; i < 60; i++) {
      try {
        const result = execFileSync(
          "powershell",
          [
            "-NoProfile",
            "-NonInteractive",
            "-Command",
            `if (Test-NetConnection -ComputerName ${DB_HOST} -Port ${DB_PORT} -InformationLevel Quiet -WarningAction SilentlyContinue) { 'ok' } else { 'no' }`,
          ],
          { encoding: "utf8", timeout: 5000 },
        );
        if (result.trim() === "ok") {
          ready = true;
          break;
        }
      } catch {}
      await browser.pause(1000);
    }
    if (!ready) {
      throw new Error(`Postgres not listening on ${DB_HOST}:${DB_PORT}\n${logTails(80)}`);
    }

    const dbExists = psql("SELECT 1 FROM pg_database WHERE datname='hotelms';", "postgres").trim();
    if (dbExists !== "1") {
      throw new Error(`hotelms database not found in cluster\n${logTails(80)}`);
    }

    const tableCount = psqlCount(
      "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';",
    );
    expect(tableCount).toBeGreaterThan(0);

    const migrationsCount = psqlCount("SELECT count(*) FROM migrations;");
    // 177 migrations exist in the repo; all should be applied
    expect(migrationsCount).toBe(177);

    // Core tables must exist
    for (const table of ["users", "roles", "model_has_roles", "properties"]) {
      const exists = psql(
        `SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='${table}';`,
      ).trim();
      if (exists !== "1") {
        throw new Error(`table public.${table} missing\n${logTails(80)}`);
      }
    }
  });

  it("created the admin account with superuser and md roles bound to a property", async function () {
    const userRow = psql(
      `SELECT id, name, email FROM users WHERE email='${adminEmail.replace(/'/g, "''")}';`,
    );
    if (!userRow) {
      throw new Error(`admin user ${adminEmail} not found\n${logTails(80)}`);
    }
    const parts = userRow.split("|");
    expect(parts[1]).toBe(adminName);
    expect(parts[2]).toBe(adminEmail);

    const roles = psql(
      `SELECT r.name FROM roles r JOIN model_has_roles mhr ON mhr.role_id=r.id JOIN users u ON u.id=mhr.model_id WHERE u.email='${adminEmail.replace(/'/g, "''")}' ORDER BY r.name;`,
    );
    expect(roles).toContain("superuser");
    expect(roles).toContain("md");

    if (propertyName && propertyName.trim()) {
      const propCount = psqlCount(
        `SELECT count(*) FROM properties WHERE lower(name)=lower('${propertyName.replace(/'/g, "''")}');`,
      );
      expect(propCount).toBeGreaterThanOrEqual(1);
    }
  });

  it("serves /up with HTTP 200 from FrankenPHP", async function () {
    // Wait for FrankenPHP to respond
    let status = 0;
    for (let i = 0; i < 60; i++) {
      try {
        const res = await fetch(`http://127.0.0.1:${HTTP_PORT}/up`, {
          method: "GET",
        });
        status = res.status;
        if (status === 200) break;
      } catch {}
      await browser.pause(1000);
    }
    if (status !== 200) {
      throw new Error(`GET http://127.0.0.1:${HTTP_PORT}/up returned ${status}\n${logTails(100)}`);
    }
  });

  it("can log in over HTTP and reach /admin/dashboard", async function () {
    // Cookie jar using Set-Cookie headers
    const cookieJar = new Map<string, string>();

    function getCookieHeader(): string {
      const parts: string[] = [];
      for (const [k, v] of cookieJar.entries()) {
        parts.push(`${k}=${v}`);
      }
      return parts.join("; ");
    }

    function applySetCookies(res: Response) {
      const setCookies = res.headers.getSetCookie
        ? res.headers.getSetCookie()
        : [];
      for (const sc of setCookies) {
        // Parse first part before ;
        const first = sc.split(";")[0];
        const eq = first.indexOf("=");
        if (eq > 0) {
          const name = first.slice(0, eq).trim();
          const value = first.slice(eq + 1).trim();
          cookieJar.set(name, value);
        }
      }
    }

    // GET /login to get session + XSRF cookie
    const loginPage = await fetch(`http://127.0.0.1:${HTTP_PORT}/login`, {
      method: "GET",
      redirect: "manual",
    });
    applySetCookies(loginPage);

    const xsrf = cookieJar.get("XSRF-TOKEN");
    if (!xsrf) {
      throw new Error(`no XSRF-TOKEN cookie from GET /login\n${logTails(100)}`);
    }

    // URL-decode the cookie value (it may be URL-encoded)
    let xsrfHeader = xsrf;
    try {
      xsrfHeader = decodeURIComponent(xsrf);
    } catch {
      // use as-is
    }

    const body = new URLSearchParams({
      email: adminEmail,
      password: adminPassword,
    });

    const postRes = await fetch(`http://127.0.0.1:${HTTP_PORT}/login`, {
      method: "POST",
      redirect: "manual",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-XSRF-TOKEN": xsrfHeader,
        Cookie: getCookieHeader(),
      },
      body: body.toString(),
    });
    applySetCookies(postRes);

    // Should redirect to /admin/dashboard for superuser/md
    expect(postRes.status).toBe(302);
    const location = postRes.headers.get("location") || "";
    expect(location).toContain("/admin/dashboard");

    // Follow to the dashboard
    const dashboardUrl = location.startsWith("http")
      ? location
      : `http://127.0.0.1:${HTTP_PORT}${location}`;
    const dashRes = await fetch(dashboardUrl, {
      method: "GET",
      redirect: "manual",
      headers: {
        Cookie: getCookieHeader(),
      },
    });
    applySetCookies(dashRes);
    expect(dashRes.status).toBe(200);
  });
});
