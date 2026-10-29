/**
 * Web e2e — authentication and the account lifecycle.
 *
 * The seeded accounts are the product's real login matrix (Task 0 of
 * docs/superpowers/plans/2026-10-21-production-walkthrough-test.md). Each one
 * must authenticate and land on the dashboard its role owns; anything less
 * means an operator cannot reach their work after a fresh install.
 *
 * Selectors are the labels a person sees (or the ids the login form exposes),
 * never CSS structure, so a restyle does not silently neuter the suite.
 */
import { expect, test } from '@playwright/test';
import {
    expectLanding,
    login,
    logout,
    readMailLog,
    resetMailLog,
} from './support/app';

/** email, password, landing path — the seeded matrix. */
const ACCOUNTS: Array<{ email: string; password: string; landing: string; role: string }> = [
    { email: 'superuser@hotelms.com', password: 'superuser@!234', landing: '/admin/dashboard', role: 'superuser' },
    { email: 'ceo@email.com', password: '1234', landing: '/admin/dashboard', role: 'md' },
    { email: 'manager@email.com', password: 'ManagerPass1!', landing: '/admin/dashboard', role: 'manager' },
    { email: 'frontdesk@email.com', password: '11111111', landing: '/frontdesk/dashboard', role: 'frontdesk' },
    { email: 'kitchen@email.com', password: '11111111', landing: '/staff/kitchen', role: 'kitchen' },
    { email: 'bar@email.com', password: '11111111', landing: '/staff/bar', role: 'bar' },
    { email: 'clean@email.com', password: '11111111', landing: '/clean', role: 'clean' },
    { email: 'laundry@email.com', password: '11111111', landing: '/staff/laundry', role: 'laundry' },
    { email: 'hr@email.com', password: '11111111', landing: '/hr/staff', role: 'hr' },
    { email: 'account@email.com', password: '11111111', landing: '/finance/dashboard', role: 'accountant' },
];

test.describe('authentication', () => {
    test('a wrong password is refused without leaving the login page', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill('manager@email.com');
        await page.locator('#password').fill('definitely-not-the-password');
        await page.getByRole('button', { name: /sign in/i }).click();

        await expect(page).toHaveURL(/\/login$/);
        // Laravel reports the failure in the form; the exact copy is the app's.
        await expect(page.locator('body')).toContainText(/credentials|password/i);
    });

    for (const account of ACCOUNTS) {
        test(`${account.role} can sign in and lands on its dashboard`, async ({ page }) => {
            await login(page, account.email, account.password);
            await expectLanding(page, account.landing);

            // The dashboard rendered for *this* user: the page answered 200 and
            // is not the login screen echoed back into the layout.
            await expect(page.locator('body')).not.toContainText('Sign in to your account');
        });
    }

    test('signing out returns to the login screen', async ({ page }) => {
        await login(page, 'manager@email.com', 'ManagerPass1!');
        await expectLanding(page, '/admin/dashboard');

        await logout(page);

        // The sidebar's logout posts and lands on the public home page, so the
        // assertion that matters is that the session is gone: a protected URL
        // must now bounce to /login.
        await page.goto('/admin/dashboard');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('a deep link survives login', async ({ page }) => {
        // Visiting a protected page while signed out must come back to that
        // page after authentication, not to the generic dashboard.
        await page.goto('/admin/reports/occupancy');
        await expect(page).toHaveURL(/\/login$/);

        await page.locator('#email').fill('manager@email.com');
        await page.locator('#password').fill('ManagerPass1!');
        await page.getByRole('button', { name: /sign in/i }).click();

        await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15_000 });
        expect(new URL(page.url()).pathname).toBe('/admin/reports/occupancy');
    });

    test('a password reset request is accepted and logged', async ({ page }) => {
        resetMailLog();

        await page.goto('/forgot-password');
        await page.locator('#email').fill('manager@email.com');

        const [response] = await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/forgot-password') &&
                    res.request().method() === 'POST',
                { timeout: 30_000 },
            ),
            page.getByRole('button', { name: /send reset link/i }).click(),
        ]);

        expect(response.status()).toBe(302);

        await expect(
            page.getByText(/we have emailed your password reset link/i),
        ).toBeVisible({ timeout: 15_000 });

        // MAIL_MAILER=log under e2e, so the message is in the application log.
        await expect
            .poll(() => readMailLog(), { timeout: 15_000 })
            .toContain('manager@email.com');
    });
});
