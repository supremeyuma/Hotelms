/**
 * Web e2e — the guest in-room surface (phone viewport).
 *
 * A guest scans the room QR, lands on their room dashboard and can act on it.
 * The token is the only thing standing between one guest and another room's
 * bill, so tampered tokens must be rejected.
 *
 * Runs in the `mobile` project. Check-in (which mints the room access token) is
 * performed in a short desktop-viewport page, because the front-desk screen is
 * built for a desk.
 */
import { expect, test } from '@playwright/test';
import { login } from './support/app';
import { sqliteScalar } from './support/db';

test.describe('guest in-room surface', () => {
    test.setTimeout(120_000);

    test('a checked-in guest reaches their room dashboard and strangers are refused', async ({
        page,
        browser,
    }) => {
        // --- arrange: check a guest in so a room token exists -----------------
        // Pick a booking whose stay has not already ended: the room access token
        // expires an hour after check-out, so checking in a past stay mints an
        // already-expired token and the QR entry point refuses it.
        const bookingId = Number(
            sqliteScalar(
                "SELECT id FROM bookings WHERE status = 'confirmed' AND check_out >= date('now') ORDER BY check_in LIMIT 1",
            ),
        );
        expect(Number.isInteger(bookingId), 'no confirmed seeded booking to check in').toBe(true);

        const desk = await browser.newPage({ viewport: { width: 1280, height: 900 } });
        await login(desk, 'frontdesk@email.com', '11111111');
        await desk.goto(`/frontdesk/bookings/${bookingId}`);
        await desk.getByRole('button', { name: /check in guest/i }).click();
        await expect
            .poll(() => sqliteScalar(`SELECT status FROM bookings WHERE id = ${bookingId}`), {
                timeout: 20_000,
            })
            .toMatch(/checked_in|active/);
        await desk.close();

        const roomId = Number(
            sqliteScalar(`SELECT room_id FROM room_access_tokens WHERE booking_id = ${bookingId} LIMIT 1`),
        );
        expect(Number.isInteger(roomId), 'check-in must mint a room access token').toBe(true);

        const token = sqliteScalar(
            `SELECT token FROM room_access_tokens WHERE booking_id = ${bookingId} LIMIT 1`,
        );

        // --- arrange: issue the room's QR key --------------------------------
        // The QR entry point addresses the room by `qr_key`, and seeded rooms
        // have none until an administrator issues one. This posts to the same
        // endpoint the admin QR action uses, from an authenticated session.
        const admin = await browser.newPage({ viewport: { width: 1280, height: 900 } });
        await login(admin, 'manager@email.com', 'ManagerPass1!');
        await admin.goto('/admin/rooms');

        const qrStatus = await admin.evaluate(async (id: number) => {
            const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            const xsrf = match ? decodeURIComponent(match[1]) : '';
            const response = await fetch(`/admin/rooms/${id}/qr`, {
                method: 'POST',
                headers: {
                    'X-XSRF-TOKEN': xsrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html,application/xhtml+xml',
                },
            });
            return response.status;
        }, roomId);

        expect(qrStatus, 'issuing the room QR must succeed').toBeLessThan(400);
        await admin.close();

        const qrKey = sqliteScalar(`SELECT qr_key FROM rooms WHERE id = ${roomId}`);
        expect(qrKey, 'the QR action must persist a qr_key').not.toBe('');

        // --- act: the QR entry point redirects to the room dashboard ----------
        await page.goto(`/guest/room-entry/${qrKey}`);
        await expect(page).toHaveURL(new RegExp(`/guest/room/${token}$`), { timeout: 20_000 });

        await expect(page.locator('body')).not.toContainText(/server error|whoops/i);
        await expect(page.locator('body')).toContainText(/\d/);

        // --- assert: a tampered token is refused ------------------------------
        const tampered = await page.goto(`/guest/room/${token}x`);
        expect(tampered?.status() ?? 0).toBeGreaterThanOrEqual(400);

        const invented = await page.goto('/guest/room/not-a-real-token');
        expect(invented?.status() ?? 0).toBeGreaterThanOrEqual(400);
    });
});
