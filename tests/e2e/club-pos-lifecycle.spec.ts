/**
 * Web e2e — the Club POS beyond the sale.
 *
 * A till that cannot void a mistake, reprint a receipt or close its shift is
 * not ready for a bar. Each action is asserted in the database: a void that
 * leaves the docket open, or a shift that never closes, breaks reconciliation.
 */
import { expect, test } from '@playwright/test';
import { sqliteCount, sqliteScalar } from './support/db';
import { login } from './support/app';
import { addFirstProduct, openDocket, payDocket, unlockTill } from './support/club';

test.describe('club POS lifecycle', () => {
    test.setTimeout(180_000);

    test('a docket can be voided, a receipt reprinted and the shift closed', async ({ page }) => {
        await login(page, 'club.bartender@email.com', '11111111');
        await expect(page).toHaveURL(/\/club\/pos/, { timeout: 20_000 });
        await unlockTill(page);

        // The void action asks for confirmation and then a reason. A single
        // listener accepts both; attaching two `once` handlers handles the same
        // dialog twice and throws.
        page.on('dialog', (dialog) => {
            void dialog.accept(dialog.type() === 'prompt' ? 'E2E mistake' : undefined);
        });

        // --- 1. Void an open docket ------------------------------------------
        const voidId = await openDocket(page);
        await addFirstProduct(page);

        // The product card's click only lands after the item posts; the void
        // control refuses an empty docket.
        await expect
            .poll(
                () => sqliteCount(`SELECT count(*) FROM pos_docket_items WHERE docket_id = '${voidId}'`),
                { timeout: 20_000 },
            )
            .toBe(1);

        // Confirm + prompt dialogs are handled by the listener above.
        await page.getByRole('button', { name: /void docket/i }).click();

        await expect
            .poll(() => sqliteScalar(`SELECT status FROM pos_dockets WHERE id = '${voidId}'`), {
                timeout: 20_000,
            })
            .toBe('voided');

        // Reload so the voided docket leaves the board and the next docket is
        // the only active one; otherwise the product click can land on the
        // voided docket and be refused.
        await page.reload();

        // --- 2. Sell something so there is a receipt to reprint --------------
        const saleId = await openDocket(page);
        await addFirstProduct(page);
        await expect
            .poll(
                () => sqliteCount(`SELECT count(*) FROM pos_docket_items WHERE docket_id = '${saleId}'`),
                { timeout: 20_000 },
            )
            .toBe(1);
        await payDocket(page, saleId);

        const printsBefore = sqliteCount('SELECT count(*) FROM pos_print_logs');

        await page.goto('/club/pos/reports/sales');
        const saleRow = page.locator('tr', { hasText: '#' + sqliteScalar(`SELECT docket_number FROM pos_dockets WHERE id = '${saleId}'`) });
        await expect(saleRow).toBeVisible({ timeout: 20_000 });
        await saleRow.getByRole('button', { name: /reprint/i }).click();

        await expect
            .poll(
                () => sqliteCount('SELECT count(*) FROM pos_print_logs'),
                { timeout: 20_000 },
            )
            .toBeGreaterThan(printsBefore);

        // --- 3. Close the shift ----------------------------------------------
        await page.goto('/club/pos');
        await page.getByRole('button', { name: /close shift/i }).click();

        const shiftDialog = page.getByRole('dialog').filter({ hasText: /close shift/i });
        await expect(shiftDialog).toBeVisible({ timeout: 10_000 });
        await shiftDialog.getByPlaceholder('0').fill('0');
        await shiftDialog.getByRole('button', { name: /close & reconcile/i }).click();

        await expect
            .poll(
                () =>
                    sqliteCount(
                        "SELECT count(*) FROM pos_sessions WHERE status = 'closed' AND user_id = (SELECT id FROM users WHERE email = 'club.bartender@email.com')",
                    ),
                { timeout: 25_000 },
            )
            .toBeGreaterThan(0);
    });
});
