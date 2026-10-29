/**
 * Web e2e — the maintenance ticket lifecycle across both views.
 *
 * A ticket is reported by staff/guest, worked by the maintenance board and
 * reviewed in the admin control room. The two views must agree: a status change
 * on the staff board that the admin screen does not show is a broken handover,
 * and that is exactly the kind of defect this walkthrough exists to catch.
 */
import { expect, test } from '@playwright/test';
import { login } from './support/app';
import { sqliteScalar } from './support/db';

test.describe('maintenance lifecycle', () => {
    test.setTimeout(120_000);

    test('staff move a ticket and the admin view shows the same state', async ({ page, browser }) => {
        // Any open seeded ticket will do; the lifecycle is what is under test.
        const ticketId = sqliteScalar(
            "SELECT id FROM maintenance_tickets WHERE status = 'open' ORDER BY id LIMIT 1",
        );
        expect(ticketId, 'the seed must contain an open maintenance ticket').not.toBe('');

        await login(page, 'manager@email.com', 'ManagerPass1!');
        await page.goto('/staff/maintenance');
        await expect(page.locator('body')).toContainText(/maintenance/i);

        // The board may render cards; take the innermost container that holds
        // both this ticket's title and its status select.
        const summary = sqliteScalar(`SELECT title FROM maintenance_tickets WHERE id = ${ticketId}`).trim();
        const ticketBlock = page
            .locator('div,li,tr')
            .filter({ hasText: summary })
            .filter({ has: page.locator('select') })
            .last();

        await expect(ticketBlock).toBeVisible({ timeout: 20_000 });
        const statusSelect = ticketBlock.locator('select').first();
        await statusSelect.selectOption('in_progress');

        await expect
            .poll(
                () => sqliteScalar(`SELECT status FROM maintenance_tickets WHERE id = ${ticketId}`),
                { timeout: 20_000 },
            )
            .toBe('in_progress');

        // The admin control room reads the same ticket.
        const admin = await browser.newPage({ viewport: { width: 1280, height: 900 } });
        await login(admin, 'ceo@email.com', '1234');
        const response = await admin.goto(`/admin/maintenance/${ticketId}`);

        expect(response?.status()).toBe(200);
        await expect(admin.locator('body')).toContainText(/in progress|in_progress/i);
        await admin.close();
    });
});
