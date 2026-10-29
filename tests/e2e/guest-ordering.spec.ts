/**
 * Web e2e — the guest ordering surface, end to end across two roles.
 *
 * A checked-in guest orders room service from their phone. The order must reach
 * the kitchen queue that staff actually work from, not just create a row: that
 * handover is the whole feature. Service requests and maintenance reports are
 * checked in the same pass because they share the surface and the same risk.
 */
import { expect, test } from '@playwright/test';
import { login } from './support/app';
import { sqliteCount, sqliteScalar } from './support/db';
import { guestPost, prepareGuestRoom } from './support/guest';

test.describe('guest ordering', () => {
    test.setTimeout(150_000);

    test('a room-service order reaches the kitchen queue', async ({ page, browser }) => {
        const room = await prepareGuestRoom(browser);

        // A real, available kitchen item from the catalogue.
        const itemName = sqliteScalar(
            "SELECT name FROM menu_items WHERE service_area = 'kitchen' AND is_available = 1 ORDER BY id LIMIT 1",
        );
        const itemPrice = Number(
            sqliteScalar(
                `SELECT price FROM menu_items WHERE service_area = 'kitchen' AND name = '${itemName.replace(/'/g, "''")}' LIMIT 1`,
            ),
        );
        expect(itemName, 'the seed must carry a kitchen menu item').not.toBe('');
        expect(Number.isFinite(itemPrice)).toBe(true);

        await page.goto(`/guest/room/${room.token}`);
        await page.waitForLoadState('domcontentloaded');

        // --- 1. The guest orders ---------------------------------------------
        const orderStatus = await guestPost(page, `/guest/room/${room.token}/orders`, {
            department: 'kitchen',
            items: [{ name: itemName, price: itemPrice, quantity: 1 }],
            payment_mode: 'pay_on_delivery',
        });
        expect(orderStatus, 'the guest order must be accepted').toBeLessThan(400);

        const orderId = sqliteScalar(
            `SELECT id FROM orders WHERE room_id = ${room.roomId} AND service_area = 'kitchen' ORDER BY id DESC LIMIT 1`,
        );
        expect(orderId, 'the order must be persisted for this room').not.toBe('');
        expect(
            sqliteScalar(`SELECT status FROM orders WHERE id = ${orderId}`),
        ).toMatch(/confirmed|pending/);

        // --- 2. It shows up on the kitchen board ------------------------------
        const kitchen = await browser.newPage({ viewport: { width: 1280, height: 900 } });
        await login(kitchen, 'kitchen@email.com', '11111111');
        await kitchen.goto('/staff/kitchen/orders');

        // The board labels cards by order number (not the booking code).
        await expect(
            kitchen.locator(`text=Order #${orderId}`).first(),
        ).toBeVisible({ timeout: 20_000 });
        await kitchen.close();

        // --- 3. The other guest actions land too ------------------------------
        const serviceRequestsBefore = sqliteCount(
            `SELECT count(*) FROM service_requests WHERE room_id = ${room.roomId}`,
        );
        const requestStatus = await guestPost(page, `/guest/room/${room.token}/service-request`, {
            type: 'cleaning',
            notes: 'E2E: please refresh the room',
        });
        expect(requestStatus).toBeLessThan(400);
        expect(
            sqliteCount(`SELECT count(*) FROM service_requests WHERE room_id = ${room.roomId}`),
        ).toBe(serviceRequestsBefore + 1);

        const ticketsBefore = sqliteCount(
            `SELECT count(*) FROM maintenance_tickets WHERE room_id = ${room.roomId}`,
        );
        const maintenanceStatus = await guestPost(page, `/guest/room/${room.token}/maintenance`, {
            type: 'plumbing',
            description: 'E2E: the shower drips',
        });
        expect(maintenanceStatus).toBeLessThan(400);
        expect(
            sqliteCount(`SELECT count(*) FROM maintenance_tickets WHERE room_id = ${room.roomId}`),
        ).toBe(ticketsBefore + 1);
    });
});
