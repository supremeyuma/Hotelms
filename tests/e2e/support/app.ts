/**
 * Shared helpers for the web end-to-end suite.
 *
 * These drive the real running app through a real browser. Keep them generic:
 * later scenarios (auth, booking, guest surface) reuse them.
 */
import { expect, type Page } from '@playwright/test';
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

/** Repository root, derived from this file's location (tests/e2e/support). */
const REPO_ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..', '..');
const LARAVEL_LOG = resolve(REPO_ROOT, 'storage', 'logs', 'laravel.log');

/**
 * Sign in through the real login form.
 *
 * Selectors come from resources/js/Pages/Auth/Login.vue: the email input has
 * `id="email"` / `type="email"`, the password input `id="password"` /
 * `type="password"`, and the submit button is the only `type="submit"`.
 *
 * Inertia performs a client-side visit, so we wait for the URL to leave /login
 * rather than for a full page load.
 */
export async function login(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/login');

    // A single dev server under load can occasionally answer the first request
    // before the page is ready; one reload is enough to tell a slow paint apart
    // from a real failure.
    const emailInput = page.locator('#email');
    try {
        await emailInput.waitFor({ state: 'visible', timeout: 15_000 });
    } catch {
        await page.goto('/login');
        await emailInput.waitFor({ state: 'visible', timeout: 15_000 });
    }

    await emailInput.fill(email);
    await page.locator('#password').fill(password);

    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15_000 }),
        page.getByRole('button', { name: /sign in/i }).click(),
    ]);
}

/**
 * Sign out through whichever logout control the current layout renders.
 *
 * There is NO `<form action="/logout">` anywhere in this app, so a form-based
 * selector matches nothing and a `toHaveCount(1)` guard on one would fail on
 * every authenticated page. The real mechanisms and their labels are:
 *
 *  - AuthenticatedLayout.vue: Inertia `<Link href="/logout" method="post"
 *    as="button">` labelled "Logout" (a bare `<button>`, inside no form)
 *  - StaffSidebar.vue: a click handler calling `router.post(route('logout'))`,
 *    labelled "Sign Out" — this is the admin/staff sidebar in use on the admin
 *    dashboard, so "Logout" alone does not cover it
 *  - VerifyEmail.vue: an Inertia `<Link :href="route('logout')">` labelled
 *    "Sign Out & Exit"
 *
 * The guest in-room surface ("Checkout") is a different action and is
 * deliberately NOT matched. Target by accessible name across both roles rather
 * than by DOM shape. Route lives in routes/auth.php behind the `auth` middleware.
 */
export async function logout(page: Page): Promise<void> {
    const control = page
        .getByRole('button', { name: /sign out|log ?out/i })
        .or(page.getByRole('link', { name: /sign out|log ?out/i }));
    await expect(control.first()).toBeVisible();

    await Promise.all([
        page.waitForURL((url) => url.pathname.endsWith('/login') || url.pathname === '/', {
            timeout: 15_000,
        }),
        control.first().click(),
    ]);
}

/**
 * Assert the browser is currently on the given path.
 * Accepts an exact path ('/dashboard') or a suffix match ('/dashboard').
 */
export async function expectLanding(page: Page, routePath: string): Promise<void> {
    const normalised = routePath.startsWith('/') ? routePath : `/${routePath}`;
    await expect
        .poll(() => new URL(page.url()).pathname, { timeout: 15_000 })
        .toBe(normalised);
}

/** Truncate the Laravel log so a spec never reads output from a previous run. */
export function resetMailLog(): void {
    writeFileSync(LARAVEL_LOG, '', 'utf8');
}

/**
 * Read the raw contents of storage/logs/laravel.log.
 *
 * Under e2e the app is configured with MAIL_MAILER=log, so confirmation emails
 * are written here in full. Callers parse what they need from the text.
 */
export function readMailLog(): string {
    try {
        return readFileSync(LARAVEL_LOG, 'utf8');
    } catch {
        return '';
    }
}
