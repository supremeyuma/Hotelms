/**
 * Web e2e — the public booking wizard, end to end.
 *
 * Search → room selection → guest details → review → pay-at-checkout →
 * confirmation. This is the revenue path, so it runs against the real
 * controllers and session state, not a mocked API.
 *
 * Payment is deliberately the offline branch ("Pay at Checkout"): the online
 * branch hands off to Flutterwave/Paystack in the browser, which a suite must
 * never call. `confirmPayment()` is the real controller either way.
 */
import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { expect, test } from '@playwright/test';

const REPO_ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');

/** A date `days` from today, as the `yyyy-mm-dd` an <input type="date"> wants. */
function dayFromNow(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return date.toISOString().slice(0, 10);
}

function bookingCountFor(email: string): number {
    // Read the e2e SQLite file directly: the web server owns it, and a
    // read-only query cannot disturb the run in progress.
    const script = [
        "$pdo = new PDO('sqlite:' . $argv[1]);",
        "$stmt = $pdo->prepare('SELECT count(*) FROM bookings WHERE guest_email = ?');",
        "$stmt->execute([$argv[2]]);",
        "echo (string) $stmt->fetchColumn();",
    ].join('\n');

    const output = execFileSync(
        'php',
        ['-r', script, resolve(REPO_ROOT, 'database', 'e2e.sqlite'), email],
        { encoding: 'utf8', timeout: 60_000 },
    );

    const count = Number(output.trim());
    if (!Number.isInteger(count)) {
        throw new Error(`could not read a booking count from sqlite: ${JSON.stringify(output)}`);
    }
    return count;
}

test.describe('booking wizard', () => {
    // The wizard is five real page visits with session round-trips; 60s is too
    // tight once the room list and its images are involved.
    test.setTimeout(150_000);

    test('a guest can book a stay and pay at checkout', async ({ page }) => {
        const guestEmail = `e2e-guest-${Date.now()}@example.test`;
        const checkIn = dayFromNow(5);
        const checkOut = dayFromNow(7);

        // --- 1. Search ------------------------------------------------------
        await page.goto('/booking');
        const dateInputs = page.locator('input[type="date"]');
        await dateInputs.nth(0).fill(checkIn);
        await dateInputs.nth(1).fill(checkOut);

        const numberInputs = page.locator('input[type="number"]');
        await numberInputs.nth(0).fill('1');
        await numberInputs.nth(1).fill('0');

        await page.locator('button[type="submit"]').click();

        await expect(page).toHaveURL(/\/booking\/rooms/, { timeout: 20_000 });

        // --- 2. Pick a room -------------------------------------------------
        const chooseRoom = page.getByRole('button', { name: /choose a room/i }).first();
        await expect(chooseRoom).toBeVisible({ timeout: 20_000 });
        await chooseRoom.click();

        // Room cards expose role="button" and contain the room name as an h4.
        // Click that title: it bubbles to the card handler, whereas the image
        // area has its own click handler (gallery).
        const roomTitle = page.locator('[role="button"]:has(h4) h4').first();
        await expect(roomTitle).toBeVisible({ timeout: 20_000 });
        await roomTitle.click();

        // The selection counter is the product's own evidence that the click
        // registered; without it the Continue button stays disabled.
        await expect(page.getByText(/^Selected$/)).toBeVisible();
        await expect(page.getByText(/^1 room$/)).toBeVisible({ timeout: 10_000 });

        await page.getByRole('button', { name: /continue with selected room/i }).click();

        // --- 3. Guest details ----------------------------------------------
        await expect(page).toHaveURL(/\/booking\/guest/, { timeout: 20_000 });

        await page.getByPlaceholder('John Doe').fill('E2E Booking Guest');
        await page.getByPlaceholder('john@example.com').fill(guestEmail);
        await page.getByPlaceholder('+234 ...').fill('08012345678');
        await page.getByPlaceholder('Business, leisure, events...').fill('leisure');
        await page
            .getByPlaceholder('Late arrival, extra towels, airport pickup, allergies...')
            .fill('Written by the e2e suite.');

        await page.locator('button[type="submit"]').click();
        await expect(page).toHaveURL(/\/booking\/review/, { timeout: 20_000 });

        // --- 4. Review and create ------------------------------------------
        await expect(page.locator('body')).toContainText('E2E Booking Guest');
        await page.getByRole('button', { name: /complete booking/i }).click();

        // --- 5. Payment: pay at checkout (offline) --------------------------
        await expect(page).toHaveURL(/\/booking\/payment\/\d+/, { timeout: 20_000 });
        await expect(page.getByText(/pay at checkout/i).first()).toBeVisible();

        await page.getByRole('button', { name: /proceed to payment/i }).click();

        // --- 6. Confirmation ------------------------------------------------
        await expect(page).toHaveURL(/\/booking\/confirmation\/\d+/, { timeout: 30_000 });
        await expect(page.getByRole('heading', { name: /booking confirmed/i })).toBeVisible();

        // The confirmation shows the booking code; it must not be a placeholder.
        const body = await page.locator('body').innerText();
        const code = body.match(/[A-Z]{2,}[A-Z0-9-]{4,}/)?.[0] ?? '';
        expect(code.length, `no booking code in:\n${body.slice(0, 600)}`).toBeGreaterThan(4);

        // ...and the reservation is a real row, not just a rendered page.
        expect(bookingCountFor(guestEmail)).toBe(1);
    });

    test('an impossible date range is refused without crashing', async ({ page }) => {
        // A check-in in the past fails validation; the wizard must stay usable
        // rather than answer with a server error.
        const response = await page.goto(
            '/booking/rooms?check_in=2000-01-01&check_out=2000-01-02&adults=1',
        );

        expect(response?.status() ?? 0).toBeLessThan(500);
        await expect(page.locator('body')).toContainText(/invalid|after|date|error/i);
    });
});
