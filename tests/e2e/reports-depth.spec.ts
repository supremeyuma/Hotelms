/**
 * Web e2e — every report page and every export format.
 *
 * A report that renders an empty table, or an export that downloads zero bytes,
 * is invisible to a status-code check. Each page here must show its own heading
 * and real numbers, and each export must come back with a document of the
 * requested type.
 */
import { expect, test } from '@playwright/test';
import { login } from './support/app';

const REPORT_PAGES: Array<{ path: string; heading: RegExp }> = [
    { path: '/admin/reports', heading: /reports?/i },
    { path: '/admin/reports/operations', heading: /report|operation|dashboard/i },
    { path: '/admin/reports/executive-overview', heading: /executive dashboard/i },
    { path: '/admin/reports/exceptions', heading: /exceptions & issues/i },
    { path: '/admin/reports/staff', heading: /staff report/i },
    { path: '/admin/reports/inventory', heading: /inventory report/i },
];

const FINANCE_PAGES = [
    '/finance/reports/profit-loss',
    '/finance/reports/balance-sheet',
    '/finance/reports/daily-revenue',
];

const EXPORTS: Array<{
    path: string;
    kind: 'spreadsheet' | 'csv';
    /** A CSV from an empty dataset is legitimate; a header proves the shape. */
    header?: string;
    /** Some exports are data-derived and may be empty on a fresh install. */
    allowEmpty?: boolean;
}> = [
    { path: '/admin/reports/occupancy/export/xlsx', kind: 'spreadsheet' },
    { path: '/admin/reports/staff/export/csv', kind: 'csv' },
    // Inventory movements only exist once stock has moved, so an empty file is
    // a truthful answer on a pristine install.
    { path: '/admin/reports/inventory/export/csv', kind: 'csv', allowEmpty: true },
    { path: '/finance/reports/revenue/export/csv', kind: 'csv' },
    // The ledger reports always carry their section headers, even at zero.
    { path: '/finance/reports/profit-loss/export/csv', kind: 'csv', header: 'Section,' },
    { path: '/finance/reports/balance-sheet/export/csv', kind: 'csv', header: 'Section,' },
    { path: '/finance/reports/daily-revenue/export/csv', kind: 'csv', header: 'Department,' },
];

test.describe('reporting depth', () => {
    test.setTimeout(180_000);

    test('every report page renders its own data', async ({ page }) => {
        await login(page, 'manager@email.com', 'ManagerPass1!');

        for (const report of REPORT_PAGES) {
            const response = await page.goto(report.path);
            expect(response?.status(), `${report.path} status`).toBe(200);

            await expect(
                page.getByRole('heading', { name: report.heading }).first(),
                `${report.path} heading`,
            ).toBeVisible({ timeout: 20_000 });

            // Inertia hydrates after the HTML arrives, so wait for real content
            // instead of reading the body immediately.
            await expect(page.locator('body')).toContainText(/\d/, { timeout: 20_000 });
            await expect(page.locator('body')).not.toContainText(/server error|whoops/i);
        }
    });

    test('every finance report renders its own data', async ({ page }) => {
        await login(page, 'account@email.com', '11111111');

        for (const path of FINANCE_PAGES) {
            const response = await page.goto(path);
            expect(response?.status(), `${path} status`).toBe(200);

            await expect(page.locator('body')).toContainText(/\d/, { timeout: 20_000 });
            await expect(page.locator('body')).not.toContainText(/server error|whoops/i);
        }
    });

    for (const entry of EXPORTS) {
        test(`export ${entry.path} is a real document`, async ({ page }) => {
            // `md` reaches both the admin report routes (manager|md) and the
            // finance routes (accountant|md), so one account covers every export.
            await login(page, 'ceo@email.com', '1234');

            const response = await page.request.get(entry.path);
            expect(response.status(), `${entry.path} status`).toBe(200);

            const body = await response.body();
            const contentType = response.headers()['content-type'] ?? '';
            const text = body.toString('utf8');

            if (entry.kind === 'csv') {
                expect(contentType).toMatch(/csv|text\/plain/i);
                if (entry.header) {
                    expect(text.startsWith(entry.header), `${entry.path} header`).toBe(true);
                }
                if (!entry.allowEmpty && !entry.header) {
                    expect(body.length, `${entry.path} must not be empty`).toBeGreaterThan(50);
                }
                if (entry.header) {
                    expect(body.length, `${entry.path} must carry its header`).toBeGreaterThan(
                        entry.header.length,
                    );
                }
            } else {
                expect(contentType).toMatch(/spreadsheet|officedocument|octet-stream/i);
                expect(body.length, `${entry.path} must not be empty`).toBeGreaterThan(100);
            }
        });
    }
});
