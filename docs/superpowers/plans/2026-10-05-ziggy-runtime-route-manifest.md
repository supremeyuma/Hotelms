# Ziggy Runtime Route Manifest Fix

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the JavaScript route manifest resolve against the host that serves it, instead of the host that built it, so a staff client or terminal can load the application from a LAN server.

**Architecture:** Replace the build-time-baked Ziggy manifest with a runtime fetch. The manifest is served by an authenticated endpoint and loaded before the Inertia app boots, so route names resolve against the current origin on every deployment without a rebuild.

**Tech Stack:** Laravel 12, PHP 8.2+, Inertia.js, Vue 3, Vite, Ziggy.

**Spec:** §9.4 (runtime route manifest blocker).

**Closes:** G27, G51, G52.

**Priority: high.** Spec §9.4 calls this "a prerequisite, not a phase item." It blocks every node other than the cloud from serving the application, so it cannot wait for Phase 6.

> **Scope-down decision (2026-10-08, verified before changing anything):**
> Task 1 verification **refuted the plan's core premise**. The installed Ziggy is **v2.6.0**, whose `BladeRouteGenerator` constructs `new Ziggy($group)` with null URL and the constructor falls back to `rtrim(url('/'), '/')` — the **request root**, not `config('app.url')`. A live serve probe with `Host: 192.168.1.50:8018` rendered `"url":"http:\/\/192.168.1.50:8018"` from `@routes`. The client never reads `resources/js/ziggy.js` (dead output), and the installed `ziggy-js` plugin falls back to `window.Ziggy` when installed without a config argument. Nothing forces the root URL in `app/`. So a LAN client already resolves every `route()` against the serving host; Tasks 2 and 3 (endpoint + fetch-before-boot) would replace a working, per-request mechanism with a redundant one and were **not executed**.
> Executed instead, per owner decision: the verified defects only — **G51** (rename the empty-leaf routes; the plan said three, verification found **21** routes across `admin.reports.`, `frontdesk.`, and `staff.`), **G52** (delete dead `ziggy.js` + gitignore), and a regression test suite (4 guards). Each change is committed.

---

## The gap this closes

### How Ziggy is wired today — verified, not assumed

Read these before changing anything, because the obvious diagnosis is wrong:

```powershell
rg -n "ziggy" resources/js/app.js resources/js/bootstrap.js
rg -n "@routes" resources/views/app.blade.php
```

What is actually true:

1. `resources/js/app.js` imports `{ ZiggyVue }` from the **`ziggy-js` package**
   and installs it with **no configuration argument**:

   ```js
   import { ZiggyVue } from 'ziggy-js'
   // ...
   vueApp.use(ZiggyVue)
   ```

2. `resources/views/app.blade.php` contains `@routes` in `<head>`. That Blade
   directive is what injects `window.Ziggy` into the page.

3. `resources/js/app.js` **never imports `./ziggy`**. The plugin therefore falls
   back to the global `window.Ziggy` that `@routes` produced.

So `resources/js/ziggy.js` — 36 KB, tracked in git, not gitignored — is
**currently dead code**. Nothing loads it. It is not the file that decides route
resolution at runtime.

That matters because the two obvious fixes target the wrong file:

- Editing `resources/js/ziggy.js` would change nothing. Nothing imports it.
- Rewriting the `@routes` Blade directive is the correct place to intervene,
  because that is the value actually reaching the client.

### The problem that remains

The manifest that `@routes` emits still bakes an absolute origin:

```
const Ziggy = {"url":"http:\/\/localhost","port":null,...}
```

`@routes` compiles the route table at boot with `config('app.url')`. A staff
client served from `http://192.168.1.50` gets a manifest whose `url` is
`http://localhost`, so every `route('...')` call in the Vue application resolves
against the wrong host. This affects `order.store`, `sanctum.csrf-cookie` and
the entire POS and booking surfaces.

Assets are built once and served from many hosts, so this cannot be fixed by
rebuilding per host.

### A second, separate defect found while verifying

The route table contains **326 routes, three of which have an empty name**:

| Generated name | Route |
|---|---|
| `admin.reports.` | `admin/reports/charts/occupancy` |
| `frontdesk.` | `frontdesk/rooms/{room}/billing` |
| `staff.` | `staff/menu/categories` |

These come from `->name('')` on routes that declare a name prefix but no leaf
name. `route('frontdesk.')` is unusable, and an empty segment is a latent
collision that will break the desktop app's offline link resolution if it is ever
persisted as a named route. This plan fixes the origin problem; the naming fix
is a separate one-line-per-route change and is called out in Task 4.

---

## Global Constraints

- **The web version must not regress.** `http://localhost` on the cloud must
  continue to work exactly as it does today.
- Do not hand-edit `resources/js/ziggy.js`. It is generated output, and after
  this plan it is also unused. Regenerating it via `php artisan ziggy:generate`
  still does not change runtime behaviour, because nothing imports it.
- No secrets in the endpoint. Routes are not sensitive; staff authentication is
  still required by the app itself.
- Commit after every task.
- Verify the fix by actually loading the app from a non-localhost origin, not by
  reading the generated file.

---

## File Structure

**Created:**
- `app/Http/Controllers/ZiggyController.php` — serves the manifest at runtime.
- `routes/web.php` — one route addition.

**Modified:**
- `resources/js/app.js` — fetch the manifest before boot.
- `resources/views/app.blade.php` — remove `@routes`, since the runtime fetch
  now supplies the manifest.
- `resources/js/ziggy.js` — delete, or leave in place as a regenerable
  development convenience. It is unreferenced either way once `@routes` is gone.

**Responsibility note:** one endpoint, one boot-time change, one Blade directive
removal. No UI work.

---

## Task 1: Prove the defect

**Files:** none.

**Interfaces:**
- Consumes: nothing.
- Produces: a reproduction that fails today.

- [x] **Step 1: Confirm the baked origin**

Verified: `resources/js/ziggy.js` did bake `"url":"http:\/\/localhost"`, but it is
**dead** — no file in `resources/js/` imports `./ziggy`, and `app.js` wires the
route helper through the installed `ziggy-js` package (`ZiggyVue`), which falls
back to `window.Ziggy` when installed without a config arg. The live source of
`window.Ziggy` is the `@routes` directive in `resources/views/app.blade.php`.

- [x] **Step 2: Confirm route calls exist in the frontend**

Verified: hundreds of `route('...')` call sites across `resources/js/` (booking,
POS, sheets), all resolving against `window.Ziggy`. Every one is relative to the
serving host — see Step 3.

- [x] **Step 3: Reproduce against a non-localhost origin**

The plan expected `order.store` to resolve to `http://localhost/order`. It does
not: building and serving with `Host: 192.168.1.50:<port>` rendered
`"url":"http:\/\/192.168.1.50:<port>"` and `order.store` resolved to
`http://192.168.1.50:<port>/order` — host-correct, no wrong-host redirect. Ziggy
**v2.6.0** (`vendor/tightenco/ziggy`) constructs the manifest with no URL
argument (`BladeRouteGenerator` → `new Ziggy($group)`), and the constructor
falls back to `rtrim(url('/'), '/')`, i.e. the request root. `app/` sets no
`forceRootUrl`/`forceScheme` (only `TrustProxies`). **The defect does not
reproduce**, so Tasks 2–3 are superseded — see the scope-down decision in the
header.

---

## Task 2: Serve the manifest at runtime — SUPERSEDED, NOT EXECUTED

The premise this task existed to fix was refuted by Task 1 Step 3: the manifest
is already built per requesting host by `@routes`. An explicit endpoint would
only add a request and a boot-time fetch. All three steps below are kept for
the record and left unchecked.

**Files:**
- Create: `app/Http/Controllers/ZiggyController.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `Tighten\Ziggy\Ziggy` (already installed with Inertia).
- Produces: `GET /ziggy.js` returning the manifest built for the requesting host.

- [ ] **Step 1: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tighten\Ziggy\Ziggy;

/**
 * Serve the Ziggy route manifest for the host that is asking.
 *
 * The manifest used to be baked at build time against a fixed origin, which
 * meant a client loading the application from a LAN server resolved every
 * route against the build host. Spec section 9.4.
 */
class ZiggyController extends Controller
{
    public function __invoke(Request $request, Ziggy $ziggy): JsonResponse
    {
        return response()->json([
            'url'      => $request->getSchemeAndHttpHost(),
            'port'     => $request->getPort(),
            'defaults' => [],
            'routes'   => $ziggy->routes()->toArray(),
        ]);
    }
}
```

`$request->getSchemeAndHttpHost()` rather than `config('app.url')` is the point
of the fix: the manifest must reflect the host actually serving the request.

- [ ] **Step 2: Register the route**

Add to `routes/web.php`, before the authenticated group so the frontend can load
it on any page including the login screen:

```php
Route::get('/ziggy.js', ZiggyController::class)->name('ziggy');
```

Routes are not sensitive. The application still requires authentication for
anything that matters, and a route manifest is a list of URL patterns.

- [ ] **Step 3: Verify the endpoint**

```powershell
php artisan serve --port=8000
```

In a second shell:

```powershell
python -c "import urllib.request, json; d=json.load(urllib.request.urlopen('http://127.0.0.1:8000/ziggy.js')); print('url =', d['url']); print('routes =', len(d['routes']))"
```

Expected: `url = http://127.0.0.1:8000` and a non-zero route count. Then request
it again with a different `Host` header and confirm the url changes.

Commit: `feat: serve the ziggy route manifest at runtime`

---

## Task 3: Load it before the Inertia app boots — PARTIALLY SUPERSEDED

Steps 2–3 were not executed (no fetch bootstrap is needed — see the scope-down
decision). Steps 4–6 were retained and adapted: they cover the two verified
defects (dead `ziggy.js`, empty route names).

**Files:**
- Modify: `resources/js/app.js` — unchanged (superseded)
- Modify: `resources/views/app.blade.php` — unchanged (superseded)
- Delete: `resources/js/ziggy.js` + `.gitignore` entry — done
- Modify: `routes/admin.php`, `routes/staff.php` — done (G51)

- [x] **Step 1: Read the current boot**

Verified: `app.js` imports `ZiggyVue` **from the `ziggy-js` package**, never
`./ziggy`; `app.blade.php:20` still contains `@routes`. The installed plugin
falls back to `window.Ziggy`, which `@routes` populates per request.

**Interfaces:**
- Consumes: Task 2's endpoint.
- Produces: a Vue app whose `route()` calls resolve against the current origin.

- [ ] **Step 1: Read the current boot**

```powershell
Get-Content resources/js/app.js
Get-Content resources/views/app.blade.php
```

Confirm the diagnosis first: `app.js` must import `ziggy-js` (the package), not
`./ziggy`, and `app.blade.php` must still contain `@routes`.

- [ ] **Step 2: Replace `@routes` with the runtime fetch** — not executed (superseded), kept for the record.

Remove `@routes` from `resources/views/app.blade.php`. Leaving it in place
means the page carries both a baked origin and a fetched one, and it is
ambiguous which one the plugin reads.

Then make the plugin wait for the manifest. Use a top-level await in the boot
sequence, or a small async bootstrap.

```js
import '../css/app.css';
import './bootstrap';

const res = await fetch('/ziggy.js', { headers: { Accept: 'application/json' } });

if (!res.ok) {
    throw new Error(`Route manifest unavailable: ${res.status}`);
}

const ziggyConfig = await res.json();

// ZiggyVue falls back to globalThis.Ziggy, so this also covers any component
// that imports route() directly rather than through injection.
window.Ziggy = ziggyConfig;

// Import the plugin from the PACKAGE. `resources/js/ziggy.js` exports a
// generated `Ziggy` config object, not `ZiggyVue` — importing the plugin from
// there is a type error.
const { ZiggyVue } = await import('ziggy-js');

// ...existing createInertiaApp / createApp code...

vueApp.use(ZiggyVue, ziggyConfig);
```

The static import at the top of `app.js` can stay for `ZiggyVue`; only the
plugin *configuration* has to be deferred. What must not happen is installing
`ZiggyVue` before the fetch resolves.

If the project does not already use top-level await, wrap the boot in an async
function and mount on resolve. Keep it minimal — do not restructure the app.

- [ ] **Step 3: Handle the failure path visibly** — not executed (superseded), kept for the record.

A blank page with a console error is worse than a clear message. Catch the fetch
failure and render a short explanation.

```js
try {
    // ... fetch and boot
} catch (error) {
    document.getElementById('app').innerHTML =
        '<div style="padding:2rem;font-family:system-ui">' +
        '<h1>Could not load the application</h1>' +
        `<p>${error.message}</p></div>`;
    throw error;
}
```

- [x] **Step 4: Decide the fate of `resources/js/ziggy.js`** — done: deleted (G52).

Nothing imports it once `@routes` is gone, so either outcome is correct:

Nothing imports it once `@routes` is gone, so either outcome is correct:

- **Delete it.** Preferred. It is 36 KB of stale, host-bound route data in the
  repository, and its presence invites the next reader to believe it is
  authoritative. If `php artisan ziggy:generate` is ever run, it reappears —
  add `resources/js/ziggy.js` to `.gitignore` in the same commit.
- **Keep it** as a developer convenience for inspecting the generated table.
  It must stay out of the Vite build. Verify with `rg "Ziggy" public/build/assets/*.js`.

Do not delete the vendor plugin. Only the generated manifest is in question.

- [x] **Step 5: Rebuild and verify the artifact no longer bakes an origin**

Done: `npm run build` succeeded, and a new guard test
(`tests/Feature/ZiggyRouteManifestTest::test_no_built_asset_bakes_a_fixed_route_origin`)
greps every `public/build/assets/*.js` for `"url":"http:\/\/localhost"` and
fails otherwise. Steps 4–5 together replaced the plan's "keep out of the build"
alternative with a provable, automatically-checked guarantee.

- [x] **Step 6: Name the empty-leaf routes**

The plan said three routes; verification found **21** — `getRoutesByName()`
collapses the duplicate `.` leaves to one key each, hiding the rest. All were
renamed in `routes/admin.php` (2: `admin.reports.charts.occupancy`,
`admin.reports.charts.inventory`) and `routes/staff.php` (19: frontdesk group —
`frontdesk.rooms.billing`, `frontdesk.rooms.billing.pay`,
`frontdesk.guest-requests.acknowledge`, `frontdesk.guest-requests.complete`;
staff menu group — `staff.menu.categories.{store,update,destroy,toggle,reorder}`,
`staff.menu.subcategories.{store,update,destroy,toggle}`,
`staff.menu.items.{store,update,destroy,toggle,reorder}`,
`staff.menu.items.images.destroy`).

`rg -n "route\('frontdesk\.'\)|route\('staff\.'\)|route\('admin\.reports\.'\)" resources/js`
still returns nothing — the routes were reachable by path but not name, so no
caller breaks. Names resolve via `route()` and the new empty-leaf guard test
(601 assertions) passes.

- [ ] **Step 6: Verify at runtime, from a second origin**

The build artifact check is necessary but not sufficient. Actually load the app.

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

From another machine on the LAN, open the app. Confirm in the browser network
tab that `route('order.store')` produces `http://<lan-ip>:8000/order`.

Commit: `feat: resolve routes against the serving host instead of the build host`

---

## Task 4: Add a regression test

A test that asserts the baked file no longer contains a fixed origin passes today
and fails after a rebuild reintroduces the problem.

**Files:**
- Create: `tests/Feature/ZiggyRouteManifestTest.php`

**Interfaces:**
- Consumes: Tasks 1–3 (in the scoped-down form: no endpoint).
- Produces: a test that fails if the manifest is ever baked again, or a
  generated manifest ever regains an empty-leaf route name.

- [x] **Step 1: Write the test**

Committed as `tests/Feature/ZiggyRouteManifestTest.php` (4 tests, 601
assertions). The endpoint-based draft below was replaced because there is no
`/ziggy.js` endpoint in the scoped-down scope. The real file asserts, in order:

1. the manifest URL resolves against the requesting host — boots the container
   with a synthetic `192.168.1.57:8000` request and confirms `(new Ziggy)`
   reports that host (not `localhost`);
2. the manifest still carries `order.store` and `sanctum.csrf-cookie`, the
   routes every Inertia POST depends on;
3. no route name ends with an empty leaf (G51 guard — 349 names iterated);
4. no built asset bakes `"url":"http:\/\/localhost"` (G27 guard — skips when
   `public/build/assets` is absent, fails if a baked manifest ever returns).

- [x] **Step 2: Verify**

`php artisan test --filter=ZiggyRouteManifestTest` → 4 passed. Full suite →
217 passed / 1417 assertions.

Commit: `test: guard the runtime route manifest against a baked origin` (`90b4ab44`)

---

## Task 5: Verify no regression on the web version

**Files:** none.

**Interfaces:**
- Consumes: Task 3.
- Produces: confirmation that the cloud deployment is unaffected.

- [ ] **Step 1: Exercise the main surfaces** — automatic portion done; the
  click-through itself is a human UAT step and remains open.

HTTP-level render checks with a non-local `Host` header showed `/login` renders
with `"url"` matching the requesting origin, and `http://localhost:8019` renders
unchanged on the loopback host — so the "cloud deployment is unchanged" claim is
confirmed for load. Dragging through booking, POS, order, and dashboard in a
real browser is left for a human.

- [x] **Step 2: Confirm the CSRF cookie route still resolves**

`rg -n "sanctum.csrf-cookie" resources/js/` → the axios wrapper resolves the
CSRF cookie route; guard test #2 asserts `sanctum.csrf-cookie` is still in the
manifest.

- [x] **Step 3: Run the full suite**

`php artisan test` → 217 passed / 1417 assertions, zero failures.

Task 5 produced no `chore:`-only commit; the rebuild was committed separately:
`chore: rebuild front-end assets after removing the dead ziggy manifest` (`2dd55106`).
The full change set for this plan, in order:

- `c7588623` fix: name all empty-leaf routes unreachable by route() (G51)
- `1d1d767a` chore: delete the dead generated ziggy.js manifest (G52)
- `90b4ab44` test: guard the runtime route manifest against a baked origin
- `2dd55106` chore: rebuild front-end assets after removing the dead ziggy manifest

---

## Exit criteria (re-scoped 2026-10-08)

- Every LAN client resolves `route()` against the serving host. **Holds** —
  Ziggy v2.6.0 builds the manifest per request (`url('/')`); reproduction
  confirmed host-correct from a non-local host.
- No built asset contains a baked origin. **Holds** — live guard in
  `ZiggyRouteManifestTest` test #4.
- The application loads and works from a LAN server origin. **Holds** (load
  verified at HTTP level; full click-through left as a human UAT step).
- The cloud deployment on `localhost` is unchanged. **Holds** — loopback render
  unchanged.
- `ZiggyRouteManifestTest` passes and would fail if the manifest were re-baked or
  a route name regressed to an empty leaf. **Holds** — 4/4 passing, plus G51/G52
  resolved.
- ~~`/ziggy.js` endpoint exists~~ — **cancelled** by the scope-down decision; the
  endpoint would replace a provably working mechanism.

Next: [Phase 3 — Club POS repair](2026-10-05-phase-3-club-pos-repair.md).
