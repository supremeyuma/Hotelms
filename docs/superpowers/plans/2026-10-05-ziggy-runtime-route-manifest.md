# Ziggy Runtime Route Manifest Fix

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the JavaScript route manifest resolve against the host that serves it, instead of the host that built it, so a staff client or terminal can load the application from a LAN server.

**Architecture:** Replace the build-time-baked Ziggy manifest with a runtime fetch. The manifest is served by an authenticated endpoint and loaded before the Inertia app boots, so route names resolve against the current origin on every deployment without a rebuild.

**Tech Stack:** Laravel 12, PHP 8.2+, Inertia.js, Vue 3, Vite, Ziggy.

**Spec:** §9.4 (runtime route manifest blocker).

**Closes:** G27, G51, G52.

**Priority: high.** Spec §9.4 calls this "a prerequisite, not a phase item." It blocks every node other than the cloud from serving the application, so it cannot wait for Phase 6.

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

- [ ] **Step 1: Confirm the baked origin**

```powershell
rg -o '"url":"[^"]*"' resources/js/ziggy.js
```

Expected: `"url":"http:\/\/localhost"`.

- [ ] **Step 2: Confirm route calls exist in the frontend**

```powershell
rg -c "route\(" resources/js/
```

Expected: a non-zero count. Every one of these resolves against the baked origin.

- [ ] **Step 3: Reproduce against a non-localhost origin**

Build, serve the built assets from a different host name, and open the app.

```powershell
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Then request the app with a `Host` header naming another machine and inspect the
rendered `order.store` URL. It will resolve to `http://localhost/order`.

A machine-level reproduction is stronger: open the app from another PC on the
LAN and watch the network tab. Every route call returns 404 or a wrong-host
redirect.

---

## Task 2: Serve the manifest at runtime

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

## Task 3: Load it before the Inertia app boots

**Files:**
- Modify: `resources/js/app.js`
- Modify: `resources/views/app.blade.php`

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

- [ ] **Step 2: Replace `@routes` with the runtime fetch**

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

- [ ] **Step 3: Handle the failure path visibly**

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

- [ ] **Step 4: Decide the fate of `resources/js/ziggy.js`**

Nothing imports it once `@routes` is gone, so either outcome is correct:

- **Delete it.** Preferred. It is 36 KB of stale, host-bound route data in the
  repository, and its presence invites the next reader to believe it is
  authoritative. If `php artisan ziggy:generate` is ever run, it reappears —
  add `resources/js/ziggy.js` to `.gitignore` in the same commit.
- **Keep it** as a developer convenience for inspecting the generated table.
  It must stay out of the Vite build. Verify with `rg "Ziggy" public/build/assets/*.js`.

Do not delete the vendor plugin. Only the generated manifest is in question.

- [ ] **Step 5: Rebuild and verify the artifact no longer bakes an origin**

```powershell
npm run build
rg -o '"url":"[^"]*"' public/build/assets/*.js
```

- [ ] **Step 6: Name the three empty routes**

Find them first — do not trust the list above if routes have changed since:

```powershell
rg -n "\->name\(''\)" routes/web.php
```

Assign each a real leaf name, then update every caller:

| Route | Suggested name | Known callers to check |
|---|---|---|
| `admin/reports/charts/occupancy` | `admin.reports.charts.occupancy` | chart components under `admin/reports` |
| `frontdesk/rooms/{room}/billing` | `frontdesk.rooms.billing` | front-desk billing Vue pages |
| `staff/menu/categories` | `staff.menu.categories.store` | the categories controller / Vue caller |

`rg -n "route\('frontdesk\.'\)|route\('staff\.'\)|route\('admin\.reports\.'\)" resources/js`
returns nothing today — these routes are reachable by path but not by name. That
is why the empty names have survived. Give them names, confirm no caller breaks,
and note the count in the commit message.

Expected: no match. The origin must not appear in any built asset.

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
- Consumes: Tasks 2–3.
- Produces: a test that fails if the manifest is ever baked again.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ZiggyRouteManifestTest extends TestCase
{
    public function test_the_manifest_endpoint_reflects_the_requesting_host(): void
    {
        $response = $this->withServerVariables([
            'HTTP_HOST' => '192.168.1.50:8000',
            'HTTPS'     => false,
        ])->getJson('/ziggy.js');

        $response->assertOk();

        $this->assertStringContainsString('192.168.1.50', $response->json('url'));
        $this->assertNotSame('http://localhost', $response->json('url'));
    }

    public function test_the_manifest_contains_named_routes(): void
    {
        $routes = $this->getJson('/ziggy.js')->json('routes');

        $this->assertArrayHasKey('order.store', $routes);
        $this->assertArrayHasKey('sanctum.csrf-cookie', $routes);
    }

    public function test_no_built_asset_bakes_a_fixed_route_origin(): void
    {
        if (! is_dir(public_path('build/assets'))) {
            $this->markTestSkipped('Front-end assets are not built.');
        }

        foreach (glob(public_path('build/assets/*.js')) as $asset) {
            $this->assertStringNotContainsString(
                '"url":"http:\\/\\/localhost"',
                (string) file_get_contents($asset),
                "Asset [{$asset}] bakes a fixed route origin.",
            );
        }
    }
}
```

The third test is the regression guard. It skips when assets are absent, so it
does not fail CI before a build — but it does fail if someone reintroduces a
baked manifest after building.

- [ ] **Step 2: Verify**

```powershell
npm run build
php artisan test --filter=ZiggyRouteManifestTest
```

Commit: `test: guard against a baked route manifest returning`

---

## Task 5: Verify no regression on the web version

**Files:** none.

**Interfaces:**
- Consumes: Task 3.
- Produces: confirmation that the cloud deployment is unaffected.

- [ ] **Step 1: Exercise the main surfaces**

Open and use each of these on `http://localhost`:

| Surface | Route | Check |
|---|---|---|
| Home | `/` | Renders |
| Booking flow | `/rooms`, `/booking/...` | Search, select, pay |
| Order | `order.store` | Places a guest order |
| Login | `/login` | Loads and submits |
| Dashboard | `/dashboard` | Loads |
| Club POS | `/club/pos` | Opens a docket |

Every network request must target `localhost`, matching today's behaviour.

- [ ] **Step 2: Confirm the CSRF cookie route still resolves**

`sanctum.csrf-cookie` is in the manifest and is load-bearing for Inertia POSTs. A
break here breaks every form.

```powershell
rg -n "sanctum.csrf-cookie" resources/js/
```

- [ ] **Step 3: Run the full suite**

```powershell
php artisan test
```

Expected: zero failures.

Commit: `chore: ziggy fix verification`

---

## Exit criteria

- `/ziggy.js` returns a manifest whose `url` matches the requesting host.
- No built asset contains a baked origin.
- The application loads and works from a LAN server origin.
- The cloud deployment on `localhost` is unchanged.
- `ZiggyRouteManifestTest` passes and would fail if the manifest were re-baked.

Next: [Phase 3 — Club POS repair](2026-10-05-phase-3-club-pos-repair.md).
