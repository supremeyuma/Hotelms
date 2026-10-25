# Production Walkthrough Remediation — Design

**Date:** 2026-10-25
**Status:** Approved in conversation; awaiting spec review
**Scope:** Repair the desktop server release gate, implement cloud pairing and
sync for the desktop server, and complete the approved offline POS terminal
workflow.

## Context

The native walkthrough in
`desktop/e2e/specs/server-setup.spec.ts` completed most of the server wizard,
but failed its backend checks:

- The repository contains 177 migrations while the staged application under
  `resources/app` contained 174.
- FrankenPHP returned HTTP 200 from `/up`, but the `/login` probe received no
  `XSRF-TOKEN` cookie. The underlying HTTP failure was not captured, so its
  root cause must be reproduced before changing application behavior.
- `@wdio/tauri-service@1.5.0` attempted to call `browser.execute`, which is
  missing from the WebdriverIO v10 browser object. The UI checks continued, but
  the compatibility warning makes the native gate unreliable.
- Windows firewall registration failed because this run was not elevated. The
  wizard surfaced the failure; this is an elevation condition, not a reason to
  silently bypass firewall configuration.
- Desktop cloud registration is currently a no-op. The Laravel cloud already
  supports single-use pairing-code redemption and sync APIs, but no admin UI
  mints codes for users.
- Tauri starts Postgres and FrankenPHP for a LAN server, but no background
  synchronization process. The terminal setup path does not start a local
  FrankenPHP server for the POS UI.

The application currently uses a single-property model. Syncable records carry
integer `property_id` values, and the approved design treats the cloud property
as canonical. This work does not introduce multi-tenancy or arbitrary
cross-property ID translation.

## Goals

1. Make the native desktop walkthrough trustworthy and fail early when the
   staged Laravel app is stale.
2. Diagnose and fix the HTTP login failure without weakening CSRF protection.
3. Allow authorized cloud administrators to mint pairing codes through the
   real web UI.
4. Pair a desktop LAN server to its canonical cloud property and run actual
   synchronization, while keeping local server operation available if cloud
   pairing is unavailable.
5. Run Club POS on a terminal's local SQLite database while disconnected,
   preserve sales across app restarts, and synchronize to the LAN server first
   with cloud fallback.
6. Prove the behavior through real application UIs and isolated local test
   services, not mocks alone.

## Design

### 1. Desktop server gate and login diagnosis

- Replace the hard-coded migration expectation with a parity assertion between
  the repository migration set and the staged app migration set. Report the
  missing/extra filenames and stop before launching the app when they differ.
- Stage the app from the current repository and rebuild the native E2E binary
  before the release walkthrough. Keep staging and build commands explicit and
  repeatable.
- Adapt or upgrade the native WebdriverIO/Tauri integration so the Tauri
  service's required browser execution APIs are available under the installed
  WebdriverIO version. Do not change production app behavior to accommodate
  the test driver.
- Extend login failure diagnostics to record the response status, safe response
  details, relevant runtime log entries, and cookie names (never cookie values).
  Reproduce the failure against the isolated app, identify its actual cause,
  and repair it without bypassing CSRF middleware or suppressing errors.
- Keep firewall registration failure visible and actionable when elevation is
  unavailable. Do not automatically elevate, weaken host firewall policy, or
  claim LAN access is open when it is not.

### 2. Cloud pairing-code administration

- Add a practical cloud admin page for creating pairing codes. Access is
  restricted to `md` and `superuser`; `manager` is not authorized to create
  node credentials.
- The page is available on both the cloud and LAN-server admin surfaces. It
  binds each code to that installation's canonical property and lets an
  administrator select an allowed node type (`lan_server` or `terminal`).
  Cloud codes pair a LAN server to the cloud; a LAN-server code pairs a POS
  terminal to that server.
- Codes are single-use and time-limited, using the existing default 15-minute
  lifetime unless the UI provides an explicit valid TTL. Display plaintext
  only once after creation and store only a one-way hash; never place
  credentials or codes in application logs or ordinary list responses.
- Continue using the existing registration endpoint for redemption. Invalid,
  expired, reused, and wrong-property codes remain indistinguishable to the
  unauthenticated caller. Registration must not mint duplicate node identities.

### 3. Desktop server cloud registration and sync

- Extend the desktop server setup UI with cloud URL and pairing details.
  Pairing is optional so the LAN server can still operate locally when cloud
  service is unreachable.
- Require HTTPS for non-loopback cloud URLs. Permit HTTP only for loopback
  endpoints used by the isolated local walkthrough.
- A successful registration uses the node ID already persisted for that
  installation, the cloud property ID, and a `lan_server` pairing code. Store
  the returned credential and peer configuration only in the app's protected
  local configuration; do not log or render the secret again.
- This release supports the current single-property identity only: the local
  property row and the cloud pairing code must use the same canonical ID
  (currently seeded as ID 1). When cloud-paired during a fresh setup, use the
  cloud property name for that row before creating the initial administrator.
  Reject a mismatched ID; do not silently match by name or rewrite unrelated
  property IDs.
- Report registration as linked only after the endpoint returns success and
  the local peer configuration is persisted. Invalid codes and transport
  failures leave local serving available, surface an explicit failed/retryable
  step, and allow retry with a newly minted code.
- Add a dedicated supervised sync worker for configured desktop-server peers.
  Run it only when synchronization is enabled and a peer is configured. Keep
  per-cycle failures observable and retry on later cycles. The desktop app must
  stop the worker it owns on shutdown and must not start unrelated Laravel
  scheduled jobs as a side effect.
- Expose link state, last successful sync, and peer errors on an authorized
  admin status surface so “linked” and retry states are visible after setup.

### 4. Offline POS terminal

- The terminal owns a durable SQLite database and serves the existing app/POS
  UI on loopback through the bundled FrankenPHP runtime. It must not expose the
  terminal's local HTTP service to the LAN.
- Setup registers one stable terminal node ID with both the LAN server and the
  cloud. Each peer's credential is persisted separately; synchronization
  preference is LAN first, then cloud fallback.
- Use the cloud admin surface to mint the cloud pairing code and the LAN-server
  admin surface to mint the terminal pairing code.
- The terminal can sell and consume stock without a reachable peer. Writes
  stay local and are captured by the existing sync engine. A terminal restart
  must preserve its local data and pending changes.
- When connectivity returns, the terminal synchronizes with available peers.
  A sale must be present exactly once and stock/reporting effects must converge.
- The terminal UI provides a visible connectivity state and an immediately
  non-repeatable submit action while a sale is being processed, consistent with
  existing project UX requirements.
- This scope does not claim verification against a physical receipt printer;
  printer model and hardware transport remain separate release checks.

## Walkthrough and acceptance criteria

All HTTP services, databases, and credentials used by the walkthrough are
isolated local/test resources. No production/development database, live cloud
deployment, or external payment service may be used. The harness may stop only
processes it started and must remove its test data at the end.

The native/browser walkthrough will:

1. Compare source and staged migrations and abort with a useful diff when stale.
2. Start the isolated cloud app and use the real admin UI to mint a code as an
   `md` or `superuser`; verify a manager is denied.
3. Complete the desktop server setup and redeem the cloud code. Verify the
   server is serving, a real browser login reaches the authenticated UI, the
   cloud records the correct node/property, and the persisted peer is usable.
4. Verify invalid and expired codes do not register nodes or report success.
5. Start the dedicated worker and verify changes move between the server and
   cloud, with errors visible when a peer is unavailable.
6. Pair a terminal with both peers, log in to its local POS UI, complete a
   sale, stop only the test peer processes, complete another sale while
   offline, restart the terminal app, restore the peers, and verify eventual
   exactly-once convergence and stock effects.
7. Pair a staff client with the LAN server and verify the intended web surface
   opens. Record firewall elevation requirements separately from application
   failures.
8. Capture screenshots for meaningful UI steps and failures, and corroborate
   visible results with the isolated database state.

The release gate passes only when the UI flow and database assertions both pass.
The current missing XSRF-cookie failure must be fixed at its reproduced root
cause; tests must not be weakened to accept unauthenticated or CSRF-disabled
behavior.

## Non-goals

- Multi-property tenancy, property-scoped users/roles, or cross-property ID
  translation.
- Deploying to or mutating the live cloud environment.
- Physical printer certification, hardware-specific drivers, or payment
  provider integration.
- Automatic Windows elevation or changes to machine-wide security policy.
- Claiming cloud registration or terminal POS readiness from mocked browser
  tests alone.

## Implementation sequencing

1. Fix staging parity, native driver compatibility, and HTTP login diagnosis.
2. Implement cloud pairing-code administration and desktop server registration.
3. Supervise desktop sync and prove server/cloud exchange.
4. Implement terminal local serving, dual-peer setup, offline operation, and
   reconciliation.
5. Run the full isolated walkthrough and clean up all test resources.
