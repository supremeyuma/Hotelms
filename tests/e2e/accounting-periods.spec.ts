/**
 * Web e2e — accounting periods.
 *
 * Finance cannot post into a period that was never opened, and a period that
 * cannot be closed cannot be reported on. This walks the operator's real
 * sequence: initialise the tax accounts, open a period, close it, reopen it —
 * asserting the database each time.
 */
import { expect, test } from '@playwright/test';
import { login } from './support/app';
import { sqliteCount, sqliteScalar } from './support/db';

function dayString(offsetDays: number): string {
    const date = new Date();
    date.setDate(date.getDate() + offsetDays);
    return date.toISOString().slice(0, 10);
}

test.describe('accounting periods', () => {
    test.setTimeout(120_000);

    test('a period is opened, closed and reopened', async ({ page }) => {
        // The page asks for confirmation before the tax-account initialisation
        // and before closing a period.
        page.on('dialog', (dialog) => void dialog.accept());

        await login(page, 'account@email.com', '11111111');
        const response = await page.goto('/finance/accounting-periods');
        expect(response?.status()).toBe(200);

        // --- initialise tax accounts (safe to re-run) ------------------------
        await page.getByRole('button', { name: /initialize tax accounts/i }).click();
        await expect(page.locator('body')).toContainText(/account|period/i, { timeout: 20_000 });

        // --- open a period ---------------------------------------------------
        await page.getByRole('button', { name: /new period/i }).click();

        const startInput = page.locator('input[type="date"]').first();
        await expect(startInput).toBeVisible({ timeout: 10_000 });

        // The form only accepts dates after the last period; honour its own min.
        const min = (await startInput.getAttribute('min')) ?? dayString(1);
        const start = min || dayString(1);

        // A full month after the start, computed from the start itself.
        const startDate = new Date(`${start}T00:00:00Z`);
        startDate.setUTCDate(startDate.getUTCDate() + 30);
        const end = startDate.toISOString().slice(0, 10);

        await startInput.fill(start);
        await page.locator('input[type="date"]').nth(1).fill(end);
        await page.getByRole('button', { name: /create period/i }).click();

        await expect
            .poll(
                () =>
                    sqliteCount(
                        `SELECT count(*) FROM accounting_periods WHERE date(start_date) = '${start}'`,
                    ),
                { timeout: 20_000 },
            )
            .toBe(1);

        const periodId = sqliteScalar(
            `SELECT id FROM accounting_periods WHERE date(start_date) = '${start}' ORDER BY id DESC LIMIT 1`,
        );

        // --- close it --------------------------------------------------------
        const closeButton = page.locator(`button[title="Close Period"]`).first();
        await expect(closeButton).toBeVisible({ timeout: 20_000 });
        await closeButton.click();

        await expect
            .poll(
                () => sqliteScalar(`SELECT is_closed FROM accounting_periods WHERE id = ${periodId}`),
                { timeout: 20_000 },
            )
            .toBe('1');

        // --- reopen it -------------------------------------------------------
        const reopenButton = page.locator(`button[title="Reopen Period"]`).first();
        await expect(reopenButton).toBeVisible({ timeout: 20_000 });
        await reopenButton.click();

        await expect
            .poll(
                () => sqliteScalar(`SELECT is_closed FROM accounting_periods WHERE id = ${periodId}`),
                { timeout: 20_000 },
            )
            .toBe('0');
    });
});
