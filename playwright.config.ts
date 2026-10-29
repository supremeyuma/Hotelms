import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for the web (Laravel + Inertia/Vue) end-to-end suite.
 *
 * The app under test is a real Laravel install served by a dedicated
 * `php artisan serve` on port 8123, backed by a throwaway SQLite file that is
 * migrated and seeded from scratch on each run. The dev `.env` is never read
 * for these overrides: everything is injected through `webServer.env`.
 */
// A port chosen away from the common 8000/8080/8123 defaults: other Laravel
// projects on a developer machine commonly sit on those, and a server started
// for them answers `/up` exactly like ours.
const PORT = 8137;
const BASE_URL = `http://127.0.0.1:${PORT}`;

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: process.env.CI ? 'github' : 'list',

    timeout: 60_000,
    expect: {
        timeout: 10_000,
    },

    use: {
        baseURL: BASE_URL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },

    projects: [
        {
            // Desktop surface: staff and admin dashboards.
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
            // The guest in-room surface is phone-shaped and runs in `mobile`.
            testIgnore: /guest-room\.spec\.ts/,
        },
        {
            // Guest in-room surface (QR ordering etc.) is phone-sized. Only the
            // specs written for it run here: the admin/auth screens have their
            // own desktop chrome and their logout controls are not on a phone
            // layout, so running them in this project proves nothing.
            name: 'mobile',
            use: { ...devices['Pixel 5'], viewport: { width: 390, height: 844 } },
            testMatch: /(smoke|guest-room)\.spec\.ts/,
        },
    ],

    webServer: {
        // CRITICAL ORDERING: `config:clear` runs FIRST and unconditionally.
        // If the framework has a cached config file (bootstrap/cache/config.php),
        // Laravel ignores the DB_CONNECTION/DB_DATABASE overrides below and falls
        // back to the dev .env — which points at the MySQL `hotelms` database.
        // The e2e server would then seed REAL dev data. Clearing the cache first
        // makes that mistake impossible.
        //
        // The dedicated SQLite file is deleted first so every run starts from an
        // empty database (the seeders are not idempotent for repeated runs on a
        // populated file). This touches ONLY database/e2e.sqlite, never .env or
        // the MySQL `hotelms` database.
        //
        // The log is truncated before migrate so specs never assert on a stale
        // run. A plain `&&` chain is used because Playwright spawns the
        // webServer via cmd.exe on Windows; shell-specific syntax would not
        // survive. `del /f /q` on a missing file still exits 0.
        command:
            'php artisan config:clear && ' +
            'del /f /q database\\e2e.sqlite database\\e2e.sqlite-journal database\\e2e.sqlite-wal database\\e2e.sqlite-shm 2>nul & ' +
            'type nul > storage\\logs\\laravel.log && ' +
            'php artisan migrate --force && ' +
            'php artisan db:seed --force && ' +
            `php artisan serve --port=${PORT}`,
        url: `${BASE_URL}/up`,
        // Never adopt a server that is already listening. Another Laravel app on
        // the same port answers `/up` too, and reusing it would run the entire
        // suite against somebody else's application. If the port is busy, the
        // run must stop and say so.
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            APP_ENV: 'e2e',
            DB_CONNECTION: 'sqlite',
            // Dedicated file — NEVER the dev MySQL `hotelms` database.
            DB_DATABASE: 'database/e2e.sqlite',
            // The dev .env points session/cache/queue at Redis (phpredis). The
            // Redis extension is not present in every dev/CI image, so the e2e
            // server uses non-Redis drivers to stay self-contained. Without
            // these, any page touching the session store 500s with
            // "Class \"Redis\" not found".
            SESSION_DRIVER: 'file',
            CACHE_STORE: 'file',
            QUEUE_CONNECTION: 'sync',
            MAIL_MAILER: 'log',
        },
    },
});
