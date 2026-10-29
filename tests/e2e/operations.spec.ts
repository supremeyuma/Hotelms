/**
 * Web e2e — the operational workflows hotel staff run every shift.
 *
 * The chain matters: a guest is checked in at the front desk, and only then is
 * their room occupied, which is what lets the kitchen attach a room order and
 * housekeeping finish a clean. Each step is asserted in the database, because a
 * screen that renders without a row behind it is the failure a release must not
 * ship.
 */
import { expect, test } from '@playwright/test';
import { login } from './support/app';
import { sqliteCount, sqliteScalar } from './support/db';

test.describe('front desk, kitchen and housekeeping', () => {
    test.setTimeout(120_000);

    test('front desk checks a guest in', async ({ page }) => {
        const bookingId = Number(
            sqliteScalar("SELECT id FROM bookings WHERE status = 'confirmed' ORDER BY id LIMIT 1"),
        );
        expect(Number.isInteger(bookingId), 'no confirmed seeded booking to check in').toBe(true);

        await login(page, 'frontdesk@email.com', '11111111');
        await page.goto(`/frontdesk/bookings/${bookingId}`);

        await page.getByRole('button', { name: /check in guest/i }).click();

        await expect
            .poll(() => sqliteScalar(`SELECT status FROM bookings WHERE id = ${bookingId}`), {
                timeout: 20_000,
            })
            .toMatch(/checked_in|active/);

        // The room assignment is what makes the room occupied downstream.
        const assigned = sqliteCount(
            `SELECT count(*) FROM booking_rooms WHERE booking_id = ${bookingId} AND checked_in_at IS NOT NULL`,
        );
        expect(assigned).toBeGreaterThan(0);
    });

    test('kitchen creates a room order and advances it to ready', async ({ page }) => {
        await login(page, 'kitchen@email.com', '11111111');
        await page.goto('/staff/kitchen/orders');

        const before = sqliteCount("SELECT count(*) FROM orders WHERE service_area = 'kitchen'");

        await page.getByRole('button', { name: /new room order/i }).click();

        // The modal's room picker only lists occupied rooms; the check-in above
        // is what puts one there. A missing option is a setup failure, not a
        // reason to skip.
        const roomSelect = page.locator('select').first();
        await roomSelect.waitFor({ state: 'visible' });
        const occupied = await roomSelect.locator('option').count();
        expect(occupied, 'no occupied room to attach a kitchen order to').toBeGreaterThan(1);

        await roomSelect.selectOption({ index: 1 });
        await page.getByRole('button', { name: '+' }).first().click();
        await page.getByRole('button', { name: /create order/i }).click();

        await expect
            .poll(
                () => sqliteCount("SELECT count(*) FROM orders WHERE service_area = 'kitchen'"),
                { timeout: 20_000 },
            )
            .toBe(before + 1);

        const orderId = Number(
            sqliteScalar("SELECT max(id) FROM orders WHERE service_area = 'kitchen'"),
        );

        // A room order is created unpaid, and the kitchen is not allowed to
        // start prep until it is paid. Record the payment through the order
        // modal first — that is the product's own rule. Scope every click to
        // this order's card: the board also shows orders created by other specs.
        const card = page.locator('[role="button"]', { hasText: `Order #${orderId}` }).first();
        await expect(card).toBeVisible({ timeout: 20_000 });
        await card.click();

        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible({ timeout: 10_000 });
        await dialog.locator('select').nth(0).selectOption('paid');
        await dialog.getByRole('button', { name: /update payment/i }).click();

        await expect
            .poll(() => sqliteScalar(`SELECT payment_status FROM orders WHERE id = ${orderId}`), {
                timeout: 20_000,
            })
            .toBe('paid');

        // Reload rather than fight the modal: the order persists, and the board
        // re-reads the paid state. This keeps the spec about the workflow, not
        // about dialog plumbing.
        await page.reload();

        // Scope to this order's card. The card is itself a button whose
        // accessible name contains "Prep", so the inner control needs an exact
        // name as well.
        const reloadedCard = page
            .locator('[role="button"]', { hasText: `Order #${orderId}` })
            .first();
        const prep = reloadedCard.getByRole('button', { name: 'Prep', exact: true });
        await prep.waitFor({ timeout: 20_000 });

        const [prepResponse] = await Promise.all([
            page.waitForResponse(
                (res) =>
                    res.url().includes('/staff/kitchen/orders/') &&
                    res.request().method() === 'PATCH',
                { timeout: 30_000 },
            ),
            prep.click(),
        ]);
        expect(prepResponse.status(), 'prep should be accepted').toBeLessThan(400);
        await expect
            .poll(() => sqliteScalar(`SELECT status FROM orders WHERE id = ${orderId}`), {
                timeout: 20_000,
            })
            .toBe('preparing');

        await reloadedCard.getByRole('button', { name: 'Ready', exact: true }).click();
        await expect
            .poll(() => sqliteScalar(`SELECT status FROM orders WHERE id = ${orderId}`), {
                timeout: 20_000,
            })
            .toBe('ready');
    });

    test('housekeeping finishes a room with the staff access code', async ({ page }) => {
        await login(page, 'clean@email.com', '11111111');
        await page.goto('/clean');

        const cleansBefore = sqliteCount("SELECT count(*) FROM cleaning_logs WHERE action = 'clean'");

        // Open a specific room by the name the board renders, so the click
        // cannot land on a navigation control that merely contains a digit.
        const roomName = sqliteScalar('SELECT name FROM rooms ORDER BY id LIMIT 1');
        const room = page.getByRole('button', { name: new RegExp(`Room ${roomName}\\b`) }).first();
        await expect(room).toBeVisible({ timeout: 20_000 });
        await room.click();

        await page.getByRole('button', { name: /finish job/i }).click();
        await page.locator('#cleaning-action-code').fill('1234');

        const [cleanResponse] = await Promise.all([
            page.waitForResponse(
                (res) =>
                    new URL(res.url()).pathname === '/clean' &&
                    res.request().method() === 'PATCH',
                { timeout: 30_000 },
            ),
            page.getByRole('button', { name: /confirm update/i }).click(),
        ]);
        expect(cleanResponse.status(), 'cleaning update should be accepted').toBeLessThan(400);

        await expect
            .poll(
                () => sqliteCount("SELECT count(*) FROM cleaning_logs WHERE action = 'clean'"),
                { timeout: 20_000 },
            )
            .toBe(cleansBefore + 1);

        const cleanRooms = sqliteCount(
            "SELECT count(*) FROM room_cleanings WHERE status = 'clean' AND cleaned_at IS NOT NULL",
        );
        expect(cleanRooms).toBeGreaterThan(0);
    });

    test('laundry desk renders its empty queue', async ({ page }) => {
        // The seed carries no laundry orders; the board must still render an
        // intentional empty state rather than a blank page.
        await login(page, 'laundry@email.com', '11111111');
        const response = await page.goto('/staff/laundry');

        expect(response?.status() ?? 0).toBeLessThan(400);
        await expect(page.locator('body')).toContainText(/laundry/i);
    });
});
