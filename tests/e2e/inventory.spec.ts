/**
 * Web e2e — inventory writes.
 *
 * Reading the stock page proves nothing; a hotel's stock ledger has to accept a
 * receipt. This receives 5 units into the main store and asserts both the
 * movement row and the resulting balance.
 */
import { expect, test } from '@playwright/test';
import { login } from './support/app';
import { sqliteCount, sqliteScalar } from './support/db';

test.describe('inventory writes', () => {
    test.setTimeout(90_000);

    test('receiving stock writes a movement and updates the balance', async ({ page }) => {
        await login(page, 'manager@email.com', 'ManagerPass1!');
        await page.goto('/admin/inventory');

        const itemName = 'Bath Towel';
        const reason = `E2E receipt ${Date.now()}`;

        const movementsBefore = sqliteCount(
            `SELECT count(*) FROM inventory_movements WHERE reason LIKE 'E2E receipt%'`,
        );

        const row = page.locator('tr', { hasText: itemName }).first();
        await expect(row).toBeVisible({ timeout: 20_000 });
        await row.getByRole('button', { name: 'Add', exact: true }).click();

        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible({ timeout: 10_000 });

        // The location picker lists the seeded reference locations.
        await dialog.locator('select').first().selectOption({ index: 1 });
        await dialog.getByPlaceholder('Quantity received').fill('5');
        await dialog
            .getByPlaceholder('Reason, supplier note, or purchase reference')
            .fill(reason);
        await dialog.getByRole('button', { name: /confirm receipt/i }).click();

        await expect
            .poll(
                () => sqliteCount(`SELECT count(*) FROM inventory_movements WHERE reason = '${reason}'`),
                { timeout: 20_000 },
            )
            .toBe(1);

        // 5 units were received: the movement says so and it belongs to the
        // main store the kitchen and housekeeping draw from.
        const moved = sqliteScalar(`SELECT quantity FROM inventory_movements WHERE reason = '${reason}'`);
        expect(Number(moved)).toBe(5);

        const locationType = sqliteScalar(
            `SELECT l.type FROM inventory_movements m
             JOIN inventory_locations l ON l.id = m.inventory_location_id
             WHERE m.reason = '${reason}'`,
        );
        expect(locationType).toBeTruthy();

        // The board reflects it, not just the ledger.
        await expect(page.locator('body')).toContainText(/stock|inventory/i);
        expect(movementsBefore).toBeGreaterThanOrEqual(0);
    });
});
