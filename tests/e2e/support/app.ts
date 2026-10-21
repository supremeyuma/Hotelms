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

    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);

    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15_000 }),
        page.getByRole('button', { name: /sign in/i }).click(),
    ]);
}

/**
 * Sign out via the authenticated logout form (POST /logout).
 * The route lives in routes/auth.php behind the `auth` middleware.
 */
export async function logout(page: Page): Promise<void> {
    // Logout is a POST form; submit the app's logout control if present.
    const logoutForm = page.locator('form[action$="/logout"]');
    await expect(logoutForm).toHaveCount(1);

    await Promise.all([
        page.waitForURL((url) => url.pathname.endsWith('/login') || url.pathname === '/', {
            timeout: 15_000,
        }),
        logoutForm.locator('button[type="submit"], input[type="submit"]').first().click(),
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
