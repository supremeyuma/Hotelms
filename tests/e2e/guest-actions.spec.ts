/**
 * Web e2e — the rest of the guest surface.
 *
 * Order history and cancellation, a checkout request with an unsettled bill,
 * and feedback. Checkout must answer with a readable refusal, not a 500: a
 * guest tapping "check out" on their phone is exactly the moment a stack trace
 * would reach a customer.
 */
import { expect, test } from '@playwright/test';
import { sqliteCount, sqliteScalar } from './support/db';
import { guestPost, prepareGuestRoom } from './support/guest';

test.describe('guest surface', () => {
    test.setTimeout(180_000);

    test('order history, cancellation, checkout guard and feedback', async ({ page, browser }) => {
        const room = await prepareGuestRoom(browser);

        await page.goto(`/guest/room/${room.token}`);
        await page.waitForLoadState('domcontentloaded');

        // --- 1. Place an order ------------------------------------------------
        const itemName = sqliteScalar(
            "SELECT name FROM menu_items WHERE service_area = 'kitchen' AND is_available = 1 ORDER BY id LIMIT 1",
        );
        const itemPrice = Number(
            sqliteScalar(
                `SELECT price FROM menu_items WHERE service_area = 'kitchen' AND name = '${itemName.replace(/'/g, "''")}' LIMIT 1`,
            ),
        );

        const orderStatus = await guestPost(page, `/guest/room/${room.token}/orders`, {
            department: 'kitchen',
            items: [{ name: itemName, price: itemPrice, quantity: 1 }],
            payment_mode: 'pay_on_delivery',
        });
        expect(orderStatus).toBeLessThan(400);

        const orderId = Number(
            sqliteScalar(
                `SELECT id FROM orders WHERE room_id = ${room.roomId} AND service_area = 'kitchen' ORDER BY id DESC LIMIT 1`,
            ),
        );
        expect(Number.isInteger(orderId)).toBe(true);

        // --- 2. History shows it ---------------------------------------------
        const history = await page.goto(`/guest/room/${room.token}/orders`);
        expect(history?.status()).toBe(200);
        await expect(page.locator('body')).toContainText(itemName, { timeout: 20_000 });

        // --- 3. Cancel it -----------------------------------------------------
        const cancelStatus = await guestPost(
            page,
            `/guest/room/${room.token}/orders/${orderId}/cancel`,
            {},
        );
        expect(cancelStatus).toBeLessThan(400);
        await expect
            .poll(() => sqliteScalar(`SELECT status FROM orders WHERE id = ${orderId}`), {
                timeout: 20_000,
            })
            .toBe('cancelled');

        // --- 4. Checkout with an unsettled bill is refused politely ----------
        const bookingStatusBefore = sqliteScalar(
            `SELECT status FROM bookings WHERE id = ${room.bookingId}`,
        );

        const checkoutStatus = await guestPost(page, `/guest/room/${room.token}/checkout`, {});
        expect(checkoutStatus, 'checkout must not answer 5xx').toBeLessThan(500);
        expect(
            sqliteScalar(`SELECT status FROM bookings WHERE id = ${room.bookingId}`),
            'an unsettled bill must not check the guest out',
        ).toBe(bookingStatusBefore);

        // --- 5. Feedback is recorded -----------------------------------------
        const feedbackBefore = sqliteCount('SELECT count(*) FROM feedback');
        const subject = `E2E feedback ${Date.now()}`;

        const feedbackStatus = await guestPost(page, `/guest/room/${room.token}/feedback`, {
            category: 'stay',
            subject,
            message: 'Written by the e2e suite.',
            rating: 5,
            is_anonymous: true,
        });
        expect(feedbackStatus).toBeLessThan(400);
        expect(sqliteCount('SELECT count(*) FROM feedback')).toBe(feedbackBefore + 1);
        expect(
            sqliteScalar(`SELECT source FROM feedback WHERE subject = '${subject}'`),
        ).toBe('guest');
    });
});
