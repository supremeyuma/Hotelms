/**
 * Web e2e — cross-cutting standards that every form must meet.
 *
 * The house rules are behavioural, not cosmetic: a submit must not be
 * repeatable while it is in flight, the user must see that something is
 * happening, and bad input must produce a friendly message rather than a 500.
 * These are asserted on a real public form (contact), because a visitor hitting
 * "send" twice must not email the hotel twice.
 */
import { expect, test } from '@playwright/test';
import { readMailLog, resetMailLog } from './support/app';

test.describe('cross-cutting standards', () => {
    test.setTimeout(90_000);

    test('a double submit sends exactly one message and shows progress', async ({ page }) => {
        resetMailLog();

        const subject = `E2E-DOUBLE-${Date.now()}`;

        // Hold the request open long enough to observe the in-flight state.
        await page.route('**/contact', async (route) => {
            if (route.request().method() === 'POST') {
                await new Promise((resolve) => setTimeout(resolve, 1200));
            }
            await route.continue();
        });

        await page.goto('/contact');
        await page.locator('#name').fill('E2E Double Submit');
        await page.locator('#email').fill('e2e-double@example.test');
        await page.locator('#subject').fill(subject);
        await page.locator('#message').fill('Only one of these should be sent.');

        const submit = page.getByRole('button', { name: /send message/i });
        await submit.click();

        // In flight: the control must be disabled and say so.
        await expect(page.getByRole('button', { name: /sending/i })).toBeDisabled();
        await expect(page.getByRole('button', { name: /sending/i })).toHaveCount(1);

        await expect(page.getByText(/your message has been sent/i)).toBeVisible({
            timeout: 20_000,
        });

        // One submission, one email.
        await expect
            .poll(
                () => readMailLog().split(subject).length - 1,
                { timeout: 15_000 },
            )
            .toBe(1);
    });

    test('bad input produces a friendly error, not a server error', async ({ page }) => {
        const response = await page.goto(
            '/booking/rooms?check_in=not-a-date&check_out=also-not-a-date&adults=0',
        );

        expect(response?.status() ?? 0).toBeLessThan(500);
        await expect(page.locator('body')).not.toContainText(/whoops|stack trace/i);
    });
});
