/**
 * Web e2e — reports, finance, HR, inventory and the Club POS entry point.
 *
 * These screens are where a hotel checks its numbers. The assertions avoid
 * "HTTP 200" on purpose: a report that renders an empty table is a bug that a
 * status code cannot see, so each page must show real data, and the export
 * links must return a non-empty document.
 */
import { expect, test } from '@playwright/test';
import { login } from './support/app';

test.describe('reporting and back office', () => {
    test.setTimeout(90_000);

    test('occupancy report shows data, linked cards and a real export', async ({ page }) => {
        await login(page, 'manager@email.com', 'ManagerPass1!');
        const response = await page.goto('/admin/reports/occupancy');

        expect(response?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: /occupancy report/i })).toBeVisible();

        // House UX standard: summary cards are links to the records behind them.
        const linkedCards = page.locator(
            'a[href*="filter=in_house"], a[href*="filter=arrivals_today"], a[href*="filter=departures_today"], a[href*="/admin/rooms"]',
        );
        expect(await linkedCards.count(), 'summary cards must link to their records').toBeGreaterThan(0);

        // The page must carry numbers, not only labels.
        await expect(page.locator('body')).toContainText(/\d/);

        const exportLink = page.getByRole('link', { name: /export xlsx/i });
        await expect(exportLink).toBeVisible();
        const href = await exportLink.getAttribute('href');
        expect(href).toBeTruthy();

        const download = await page.request.get(href!);
        expect(download.status()).toBe(200);
        const body = await download.body();
        expect(body.length, 'the XLSX export must not be empty').toBeGreaterThan(500);
    });

    test('accountant sees the finance dashboard and can export revenue', async ({ page }) => {
        await login(page, 'account@email.com', '11111111');

        const dashboard = await page.goto('/finance/dashboard');
        expect(dashboard?.status()).toBe(200);
        await expect(page.locator('body')).not.toContainText(/server error|whoops/i);

        const revenue = await page.goto('/finance/reports/revenue');
        expect(revenue?.status()).toBe(200);
        await expect(page.locator('body')).toContainText(/\d/);

        const csv = await page.request.get('/finance/reports/revenue/export/csv');
        expect(csv.status()).toBe(200);
        const text = await csv.text();
        expect(text.trim().length, 'the revenue CSV must not be empty').toBeGreaterThan(20);
    });

    test('HR staff register lists the seeded staff', async ({ page }) => {
        await login(page, 'hr@email.com', '11111111');
        const response = await page.goto('/hr/staff');

        expect(response?.status()).toBe(200);
        // Seeded operational accounts have staff profiles; the register must
        // show at least one of them rather than an empty table.
        await expect(page.locator('body')).toContainText(/@email\.com/);
    });

    test('inventory shows its items and the default stock locations', async ({ page }) => {
        await login(page, 'manager@email.com', 'ManagerPass1!');
        const response = await page.goto('/admin/inventory');

        expect(response?.status()).toBe(200);
        // Seeded consumables, and the reference locations a fresh install needs
        // for housekeeping and kitchen stock (see the inventory-locations
        // migration).
        await expect(page.locator('body')).toContainText(/Bath Towel/i);

        const locations = await page.goto('/admin/inventory-locations');
        expect(locations?.status()).toBe(200);
        await expect(page.locator('body')).toContainText(/Main Store/i);
    });

    test('club POS opens for club staff', async ({ page }) => {
        await login(page, 'club.bartender@email.com', '11111111');

        await expect
            .poll(() => new URL(page.url()).pathname, { timeout: 20_000 })
            .toBe('/club/pos');

        await expect(page.locator('body')).not.toContainText(/server error|whoops/i);
        await expect(page.locator('body')).toContainText(/club|pos|docket/i);
    });
});
