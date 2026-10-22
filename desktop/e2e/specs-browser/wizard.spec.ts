// The desktop wizard renderer, driven in plain Chrome with mocked Tauri IPC.
//
// Selectors are verified in desktop/src/main.ts:
//   role cards [data-role], back [data-back], steps [data-step] with
//   .step__badge .badge and .step__message, done [data-open], pairing
//   .pairing__row > canvas + code, failure [data-retry] in .panel--err.
//
// `expect` is the global one WebdriverIO installs (expect-webdriverio), so the
// element matchers auto-wait.

import { openWizardPage } from "./mocks";

describe("Desktop wizard fast loop (browser mode)", () => {
  beforeEach(async () => {
    // The preload that installs the mocked IPC has to be in place before the
    // navigation, because `main.ts` calls `setup_status` while it boots.
    await openWizardPage(browser);
  });

  it("shows the role select with all three cards and its heading", async () => {
    await expect($("h2")).toHaveText("How will this PC be used?");
    await expect($('[data-role="server"]')).toBeDisplayed();
    await expect($('[data-role="terminal"]')).toBeDisplayed();
    await expect($('[data-role="staff_client"]')).toBeDisplayed();
  });

  it("renders each role's form heading and submit label from ROLE_DEFS", async () => {
    await $('[data-role="server"]').click();
    await expect($("h2")).toHaveText("Server setup");
    await expect($('button[type="submit"]')).toHaveText("Start server setup");

    await $("[data-back]").click();
    await expect($("h2")).toHaveText("How will this PC be used?");

    await $('[data-role="terminal"]').click();
    await expect($("h2")).toHaveText("Terminal setup");
    await expect($('button[type="submit"]')).toHaveText("Start terminal setup");

    await $("[data-back]").click();
    await expect($("h2")).toHaveText("How will this PC be used?");

    await $('[data-role="staff_client"]').click();
    await expect($("h2")).toHaveText("Connect to a server");
    await expect($('button[type="submit"]')).toHaveText("Connect");
  });

  it("renders the mocked setup_plan in order with pending badges", async () => {
    const plan = [
      { id: "unpack_postgres", title: "Unpack Postgres" },
      { id: "create_admin", title: "Create the first administrator" },
      { id: "bind_network", title: "Bind the network interface" },
    ];
    await openWizardPage(browser, { plan });

    await $('[data-role="server"]').click();

    const steps = await $$("[data-step]");
    expect(steps.length).toBe(plan.length);

    // `$$()` returns an element list whose `map()` is async, so the ids are
    // collected with a plain loop instead of `Promise.all(list.map(...))`.
    const ids: (string | null)[] = [];
    for (const step of steps) {
      ids.push(await step.getAttribute("data-step"));
    }
    expect(ids).toEqual(plan.map((step) => step.id));

    for (const step of plan) {
      // The badge is styled uppercase, so the text is compared case-insensitively.
      await expect(
        $(`[data-step="${step.id}"] .step__badge .badge`),
      ).toHaveText("pending", { ignoreCase: true });
    }
  });

  it("flips step badges and shows the Cause: prefix when setup://step fires", async () => {
    await $('[data-role="server"]').click();

    await browser.tauri.emitEvent("setup://step", {
      id: "unpack_postgres",
      title: "Unpack Postgres",
      status: "succeeded",
      message: null,
    });
    await expect(
      $('[data-step="unpack_postgres"] .step__badge .badge'),
    ).toHaveText("done", { ignoreCase: true });

    await browser.tauri.emitEvent("setup://step", {
      id: "generate_env",
      title: "Generate environment",
      status: "failed",
      message: "permission denied",
    });
    await expect(
      $('[data-step="generate_env"] .step__badge .badge'),
    ).toHaveText("failed", { ignoreCase: true });

    const message = await $('[data-step="generate_env"] .step__message');
    await expect(message).toBeDisplayed();
    const text = await message.getText();
    expect(text).toContain("Cause:");
    expect(text).toContain("permission denied");
  });

  it("shows the done panel with the open button and the pairing row", async () => {
    const uri = "moorelife://pair?addr=10.0.0.5%3A8000";
    await openWizardPage(browser, {
      pairing: {
        addresses: ["10.0.0.5:8000"],
        uris: [uri],
        name: "Fast Loop Server",
        version: "0.1.0",
        port: 8000,
      },
    });
    await $('[data-role="server"]').click();

    await browser.tauri.emitEvent("setup://done", { ok: true, port: 8000 });

    await expect($("[data-open]")).toBeDisplayed();

    // The pairing block is appended after `discovery_pairing` resolves.
    await browser.waitUntil(
      async () => (await $(".pairing__row")).isExisting(),
      { timeout: 10000, timeoutMsg: "the pairing row never rendered" },
    );

    const pairingRow = await $(".pairing__row");
    await expect(pairingRow).toBeDisplayed();
    await expect(pairingRow.$("canvas")).toBeExisting();

    const code = await pairingRow.$("code");
    await expect(code).toBeDisplayed();
    expect(await code.getText()).toContain(uri);
  });

  it("shows the failure panel with a retry button and the failed step", async () => {
    await $('[data-role="server"]').click();

    await browser.tauri.emitEvent("setup://step", {
      id: "migrate",
      title: "Run migrations",
      status: "failed",
      message: "boom",
    });
    await browser.tauri.emitEvent("setup://done", {
      ok: false,
      error: "Setup failed",
    });

    await expect($("[data-retry]")).toBeDisplayed();
    const panelText = await $(".panel--err").getText();
    expect(panelText).toContain("Step");
    expect(panelText).toContain("migrate");
  });

  it("guards against a double submit with a single invoke", async () => {
    const runServer = await browser.tauri.mock("setup_run_server");
    await runServer.mockResolvedValue(undefined);

    await $('[data-role="server"]').click();

    await $("#admin-name").setValue("Admin");
    await $("#admin-email").setValue("admin@test.com");
    await $("#admin-password").setValue("pass");

    const submit = await $('button[type="submit"]');
    await submit.click();
    await submit.click();

    await expect(submit).toHaveAttribute("data-disabled");

    await runServer.update();
    expect(runServer.mock.calls.length).toBe(1);
  });

  it("sends the typed form values and null for a blank property name", async () => {
    const runServer = await browser.tauri.mock("setup_run_server");
    await runServer.mockResolvedValue(undefined);

    await $('[data-role="server"]').click();

    await $("#admin-name").setValue("Alice");
    await $("#admin-email").setValue("alice@example.com");
    await $("#admin-password").setValue("secret");
    await $("#property-name").setValue("   ");

    await $('button[type="submit"]').click();

    await runServer.update();
    expect(runServer.mock.calls.length).toBe(1);

    const args = runServer.mock.calls[0][0] as {
      adminName: string;
      adminEmail: string;
      adminPassword: string;
      propertyName: string | null;
    };
    expect(args.adminName).toBe("Alice");
    expect(args.adminEmail).toBe("alice@example.com");
    expect(args.adminPassword).toBe("secret");
    expect(args.propertyName).toBeNull();
  });
});
