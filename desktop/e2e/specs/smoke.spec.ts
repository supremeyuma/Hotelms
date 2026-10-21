// Smoke spec for the native desktop harness.
//
// Proves the built binary launches under the embedded WebDriver provider and
// shows the first screen of the wizard — the role picker — before any setup
// runs. Selectors are verified in desktop/src/main.ts (`renderRoleSelect`).
//
// The app-data reset runs here (in addition to any global hook) so this suite is
// self-contained: a previous interrupted run must not leave `setup-state.json`
// in a `running` state, or `boot()` would resume mid-setup and this spec would
// assert against the wrong screen. See helpers/appdata.ts.

import { resetAppData } from "../helpers/appdata";

describe("desktop wizard — smoke", () => {
  before(() => {
    resetAppData();
  });

  it("shows the role-selection screen with all three roles", async () => {
    // The window is created by the builder; refresh so we are sure we are
    // observing the app, not a blank first paint.
    await browser.refresh();

    const heading = await $("//*[normalize-space(text())='How will this PC be used?']");
    await heading.waitForDisplayed({ timeout: 60000 });
    await expect(heading).toBeDisplayed();

    for (const role of ["server", "terminal", "staff_client"]) {
      const card = await $(`[data-role="${role}"]`);
      await card.waitForDisplayed({ timeout: 30000 });
      await expect(card).toBeDisplayed();
    }
  });
});
