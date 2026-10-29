/**
 * Web e2e — laundry, end to end.
 *
 * No laundry services are seeded, so the spec creates one the way an operator
 * would (staff item page), has the checked-in guest request it from their room,
 * and then moves the order on the laundry board. Every step is asserted in the
 * database, because laundry is billing: an order that only exists on screen is
 * a charge that never lands.
 */
import { expect, test } from '@playwright/test';
import { login } from './support/app';
import { sqliteCount, sqliteScalar } from './support/db';
import { guestPost, prepareGuestRoom } from './support/guest';

test.describe('laundry lifecycle', () => {
    test.setTimeout(150_000);

    test('an item is created, the guest requests it, and the board advances it', async ({
        page,
        browser,
    }) => {
        const room = await prepareGuestRoom(browser);
        const itemName = `E2E Dry Clean ${Date.now()}`;

        // --- 1. Staff create the laundry service -----------------------------
        await login(page, 'laundry@email.com', '11111111');
        await page.goto('/staff/laundry-items');
        await page.getByRole('button', { name: /add new item/i }).click();

        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible({ timeout: 10_000 });
        await dialog.getByPlaceholder('e.g. Suit Dry Cleaning').fill(itemName);
        await dialog.getByPlaceholder('0.00').fill('2500');
        await dialog.getByRole('button', { name: /save|create/i }).click();

        await expect
            .poll(
                () => sqliteCount(`SELECT count(*) FROM laundry_items WHERE name = '${itemName}'`),
                { timeout: 20_000 },
            )
            .toBe(1);

        const itemId = sqliteScalar(
            `SELECT id FROM laundry_items WHERE name = '${itemName}' ORDER BY id DESC LIMIT 1`,
        );

        // --- 2. The guest requests laundry ----------------------------------
        await page.goto(`/guest/room/${room.token}`);
        await page.waitForLoadState('domcontentloaded');

        const status = await guestPost(page, `/guest/room/${room.token}/laundry`, {
            booking_id: room.bookingId,
            items: [{ laundry_item_id: Number(itemId), quantity: 2 }],
            payment_mode: 'postpaid',
        });
        expect(status, 'the guest laundry request must be accepted').toBeLessThan(400);

        await expect
            .poll(
                () =>
                    sqliteCount(
                        `SELECT count(*) FROM laundry_orders WHERE room_id = ${room.roomId}`,
                    ),
                { timeout: 20_000 },
            )
            .toBeGreaterThan(0);

        const orderId = sqliteScalar(
            `SELECT id FROM laundry_orders WHERE room_id = ${room.roomId} ORDER BY id DESC LIMIT 1`,
        );

        // --- 3. The laundry board advances it -------------------------------
        await page.goto('/staff/laundry');
        await expect(page.locator('body')).toContainText(/laundry/i);

        const statusSelect = page.locator('select').first();
        await statusSelect.waitFor({ state: 'visible', timeout: 20_000 });
        await statusSelect.selectOption('ready');

        // The board requires an explicit save: the icon button beside the select
        // has no accessible name, so it is addressed by position.
        await statusSelect.locator('xpath=following-sibling::button[1]').click();

        await expect
            .poll(
                () => sqliteScalar(`SELECT status FROM laundry_orders WHERE id = ${orderId}`),
                { timeout: 20_000 },
            )
            .toBe('ready');
    });
});
