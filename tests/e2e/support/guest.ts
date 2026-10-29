/**
 * Arranges the state the guest surface needs: a checked-in booking, a room
 * access token, and an issued QR key.
 *
 * Three specs need exactly this (the QR surface, laundry and room-service
 * ordering), and each part is a product workflow rather than a test shortcut:
 * check-in mints the token, and the admin QR action mints the key.
 */
import type { Browser, Page } from '@playwright/test';
import { expect } from '@playwright/test';

import { login } from './app';
import { sqliteScalar } from './db';

export interface GuestRoom {
    bookingId: number;
    roomId: number;
    token: string;
    qrKey: string;
}

export async function prepareGuestRoom(browser: Browser): Promise<GuestRoom> {
    // A booking whose stay has not ended: the room token expires an hour after
    // check-out, so a past stay would mint an already-expired token.
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

    return { bookingId, roomId, token, qrKey };
}

/**
 * POST as the guest from their own room page, carrying the session's CSRF
 * cookie. Returns the HTTP status so callers can assert the request was taken.
 */
export async function guestPost(page: Page, path: string, body: unknown): Promise<number> {
    return page.evaluate(
        async ({ path, body }) => {
            const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            const xsrf = match ? decodeURIComponent(match[1]) : '';
            const response = await fetch(path, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html,application/xhtml+xml',
                },
                body: JSON.stringify(body),
            });
            return response.status;
        },
        { path, body },
    );
}
