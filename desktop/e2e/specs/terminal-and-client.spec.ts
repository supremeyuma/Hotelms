// End-to-end proof that a terminal pairs with a running LAN server, pulls the
// reference snapshot and can then be signed into, and that a staff client
// points at that server without creating a database of its own.
//
// The server is a real prerequisite: the terminal exchanges a single-use
// pairing code for a credential at POST /api/sync/register, and pulls the
// snapshot from the same peer. So this spec boots a real server first (the same
// nine-step run the server spec performs), mints a code the way an operator
// would, and then drives the terminal wizard against it.
//
// Depends on: desktop/src-tauri/src/setup/mod.rs (run_terminal),
// app/Http/Controllers/Sync/NodeRegistrationController.php,
// app/Console/Commands/SyncRefreshReference.php.

import { existsSync } from "node:fs";

import { resetAppData } from "../helpers/appdata";
import {
  HTTP_PORT,
  logTails,
  stopLeftoverServers,
} from "../helpers/server";
import {
  chooseRole,
  expectNoFailurePanel,
  runServerSetup,
  stepMessage,
  stepStatus,
  waitForSetupPanel,
} from "../helpers/wizard";
import {
  mintPairingCode,
  readAppEnv,
  registeredNode,
  sqliteScalar,
  terminalDatabasePath,
} from "../helpers/terminal";

describe("desktop wizard — terminal pairing and staff client", function () {
  this.timeout(900000);

  const admin = {
    name: "E2E Pairing Admin",
    email: `e2e-pairing-${Date.now()}@example.test`,
    password: "E2e-Pairing-Passw0rd!",
    property: "E2E Pairing Property",
  };
  let propertyId = 0;

  before(async function () {
    const stopped = stopLeftoverServers();
    if (stopped.length > 0) {
      console.log(`[terminal-e2e] stopped leftovers:\n  ${stopped.join("\n  ")}`);
    }

    const removed = resetAppData();
    if (removed.length > 0) {
      console.log(`[terminal-e2e] resetAppData removed:\n  ${removed.join("\n  ")}`);
    }

    // Real Postgres + migrations + seed, exactly as a server install does it.
    console.log("[terminal-e2e] booting the server");
    await browser.refresh();
    await runServerSetup(admin);
    await expectNoFailurePanel();
    console.log("[terminal-e2e] server ready");

    const configured = readAppEnv("SYNC_PROPERTY_ID");
    if (!configured) {
      throw new Error(`the server did not record a property id\n${logTails(60)}`);
    }
    propertyId = Number(configured);
  });

  after(async function () {
    try {
      const stopped = stopLeftoverServers();
      if (stopped.length > 0) {
        console.log(`[terminal-e2e] after(): stopped:\n  ${stopped.join("\n  ")}`);
      }
    } catch (error) {
      console.log(`[terminal-e2e] after() failed to stop servers: ${error}`);
    }
  });

  it("pairs a terminal with the server and pulls the snapshot", async function () {
    const pairingCode = mintPairingCode(propertyId);

    await chooseRole("terminal");
    await $("#property-code").waitForDisplayed({ timeout: 60000 });
    await $("#property-code").setValue(String(propertyId));
    await $("#pairing-code").setValue(pairingCode);
    await $('input[name="serverUrl"]').setValue(`http://127.0.0.1:${HTTP_PORT}`);

    const submit = await $('button[type="submit"]');
    await submit.waitForClickable({ timeout: 30000 });
    await submit.click();

    await waitForSetupPanel();
    await expectNoFailurePanel();

    for (const id of [
      "create_database",
      "migrate",
      "register_node",
      "pull_snapshot",
    ]) {
      const status = await stepStatus(id);
      if (status !== "done" && status !== "skipped") {
        throw new Error(
          `terminal step "${id}" not successful (status=${status}, message=${await stepMessage(id)})\n${logTails(60)}`,
        );
      }
    }

    // Registration really happened on the server: one node, carrying a
    // credential, paired for this property.
    const node = registeredNode(propertyId);
    if (!node) {
      throw new Error(`the server registered no node for property ${propertyId}\n${logTails(80)}`);
    }
    expect(node.type).toBe("terminal");
    expect(node.credential_present).toBe(true);

    // The snapshot pull really landed: staff accounts exist in the terminal's
    // own SQLite file, which is what makes "log in on the terminal" possible.
    const database = terminalDatabasePath();
    if (!existsSync(database)) {
      throw new Error(`terminal database not created at ${database}\n${logTails(60)}`);
    }

    const userCount = Number(sqliteScalar("SELECT count(*) FROM users"));
    expect(userCount).toBeGreaterThan(0);

    const adminCount = Number(
      sqliteScalar(
        `SELECT count(*) FROM users WHERE email = '${admin.email.replace(/'/g, "''")}'`,
      ),
    );
    expect(adminCount).toBe(1);

    const pulled = await stepMessage("pull_snapshot");
    expect(pulled ?? "").not.toBe("");
  });

  it("points a staff client at the server without a local database", async function () {
    // The pairing link is what the server actually shows; the wizard must
    // accept it and store an http address.
    const pairingLink = `moorelife://pair?v=1&host=127.0.0.1&port=${HTTP_PORT}&name=MooreLife`;

    console.log("[staff-e2e] choosing role");
    await chooseRole("staff_client");
    console.log("[staff-e2e] role chosen");

    await $("#server-url").waitForDisplayed({ timeout: 60000 });
    await $("#server-url").setValue(pairingLink);
    console.log("[staff-e2e] form filled");

    const submit = await $('button[type="submit"]');
    await submit.waitForClickable({ timeout: 30000 });
    await submit.click();
    console.log("[staff-e2e] submitted");

    await waitForSetupPanel(300000);
    console.log("[staff-e2e] panel rendered");
    await expectNoFailurePanel();

    expect(await stepStatus("discover_server")).toBe("skipped");
    expect(await stepStatus("open_window")).toBe("done");

    const open = await $("[data-open]");
    await expect(open).toBeDisplayed();

    // Stored as the HTTP base URL, never the pairing scheme. A staff client
    // keeps no local database: its environment declares an in-memory SQLite
    // connection, not a file beside the wizard's state.
    expect(readAppEnv("LAN_SERVER_URL")).toBe(`http://127.0.0.1:${HTTP_PORT}`);
    expect(readAppEnv("DB_CONNECTION")).toBe("sqlite");
    expect(readAppEnv("DB_DATABASE")).toBe(":memory:");

    // The done panel shows a usable address, not `moorelife://`.
    const panel = await $(".panel--ok");
    const text = await panel.getText();
    expect(text).toContain(`http://127.0.0.1:${HTTP_PORT}`);
    expect(text).not.toContain("moorelife://");
  });
});
