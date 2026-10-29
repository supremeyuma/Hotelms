// Drives the wizard screens that more than one spec needs.
//
// The server walkthrough is the prerequisite for the terminal and staff-client
// roles: a terminal pairs against a *running* LAN server, so its spec has to
// boot one first. That boot is the same nine-step wizard run the server spec
// performs, so it lives here instead of being copied.

/// <reference types="@wdio/globals/types" />

export interface AdminAccount {
  name: string;
  email: string;
  password: string;
  property?: string;
}

/**
 * Choose a role from the role picker and wait for its form.
 *
 * `refresh()` first: after a completed run the wizard is left on a done panel
 * with no "change my choice" control, and `boot()` re-renders the picker when
 * `setup_status` reports that nothing is running.
 */
export async function chooseRole(
  role: "server" | "terminal" | "staff_client",
): Promise<void> {
  await browser.refresh();

  const card = await $(`[data-role="${role}"]`);
  await card.waitForDisplayed({ timeout: 60000 });

  // The recorder has to be installed before the run starts: `renderDone()`
  // replaces `#root`, so the step rows are gone by the time a spec could read
  // them. Events are the only durable record of what each step reported.
  await installStepRecorder();

  await card.click();
}

interface RecordedStep {
  status: string | null;
  message: string | null;
}

/**
 * Record `setup://step` events on the page, keyed by step id.
 *
 * Survives rebuilding `#root`; does not survive a reload, which is why
 * {@link chooseRole} installs it again after every refresh.
 */
export async function installStepRecorder(): Promise<void> {
  await browser.execute(() => {
    const page = window as unknown as {
      __e2eSteps?: Record<string, RecordedStep>;
      __e2eStepsInstalled?: boolean;
      __TAURI__?: {
        event?: { listen: (name: string, cb: (event: { payload?: unknown }) => void) => unknown };
      };
    };

    if (page.__e2eStepsInstalled) {
      return;
    }
    page.__e2eStepsInstalled = true;
    page.__e2eSteps = {};

    page.__TAURI__?.event?.listen("setup://step", (event) => {
      const payload = (event?.payload ?? {}) as {
        id?: string;
        status?: string;
        message?: string | null;
      };
      const id = payload.id ?? "";
      if (!id) return;

      const status =
        payload.status === "succeeded"
          ? "done"
          : payload.status === "started"
            ? "running"
            : (payload.status ?? null);

      page.__e2eSteps![id] = { status, message: payload.message ?? null };
    });
  });
}

/** The recorded step, or null when no event for it was seen. */
async function recordedStep(stepId: string): Promise<RecordedStep | null> {
  const steps = await browser.execute((id: string) => {
    const page = window as unknown as { __e2eSteps?: Record<string, unknown> };
    return page.__e2eSteps?.[id] ?? null;
  }, stepId);

  return (steps as RecordedStep | null) ?? null;
}

/**
 * Fill and submit the server form, then wait until the run reaches a terminal
 * panel. Returns nothing: the caller asserts the evidence it cares about.
 */
export async function runServerSetup(admin: AdminAccount): Promise<void> {
  await chooseRole("server");

  await $("#admin-name").waitForDisplayed({ timeout: 60000 });
  await $("#admin-name").setValue(admin.name);
  await $("#admin-email").setValue(admin.email);
  await $("#admin-password").setValue(admin.password);
  if (admin.property) {
    await $("#property-name").setValue(admin.property);
  }

  const submit = await $('button[type="submit"]');
  await submit.waitForClickable({ timeout: 30000 });
  await submit.click();

  await waitForSetupPanel();
}

/**
 * Wait until the run reaches a terminal panel.
 *
 * Both panels are checked. Waiting for `[data-open]` alone is wrong for the
 * terminal role: `launchTarget()` returns null there (a terminal does not open
 * the web app), so its done panel carries no open button at all.
 */
export async function waitForSetupPanel(timeout = 600000): Promise<void> {
  await browser.waitUntil(
    async () => {
      if (await (await $(".panel--ok")).isExisting()) return true;
      if (await (await $(".panel--err")).isExisting()) return true;
      return false;
    },
    { timeout, interval: 1000, timeoutMsg: "setup did not finish" },
  );
}

/** Throw with the panel's text when the last run failed. */
export async function expectNoFailurePanel(): Promise<void> {
  const retry = await $("[data-retry]");
  if (await retry.isExisting()) {
    const panel = await $(".panel--err");
    const text = await panel.getText().catch(() => "");
    throw new Error(`setup failed with panel:\n${text}`);
  }
}

/** The badge text of one step row ("done", "skipped", "failed", ...). */
export async function stepStatus(stepId: string): Promise<string | null> {
  const recorded = await recordedStep(stepId);
  if (recorded) {
    return recorded.status;
  }

  const badge = await $(`[data-step="${stepId}"] .step__badge`);
  if (!(await badge.isExisting())) {
    return null;
  }
  return (await badge.getText()).trim().toLowerCase();
}

/** The message under one step row, or null when empty. */
export async function stepMessage(stepId: string): Promise<string | null> {
  const recorded = await recordedStep(stepId);
  if (recorded) {
    return recorded.message;
  }

  const el = await $(`[data-step="${stepId}"] .step__message`);
  if (!(await el.isExisting())) {
    return null;
  }
  const text = (await el.getText()).trim();
  return text === "" ? null : text;
}
