/**
 * Web e2e — a signed-in user cannot open another role's surface.
 *
 * Every role-specific route is protected by Spatie role middleware or a policy,
 * which answers 403. This is the assertion that matters before a release: a
 * missing middleware shows up as a 200, and nothing else in the suite would
 * notice.
 *
 * Only cross-role pairs are listed. Two roles legitimately share some pages
 * (a manager may open front-desk screens), so the pairs below are the ones the
 * route table restricts to a disjoint role set.
 */
import { expect, test } from '@playwright/test';
import { login } from './support/app';

const DENIED: Array<{ email: string; password: string; path: string; why: string }> = [
    {
        email: 'kitchen@email.com',
        password: '11111111',
        path: '/admin/dashboard',
        why: 'kitchen staff are not administrators',
    },
    {
        email: 'frontdesk@email.com',
        password: '11111111',
        path: '/finance/dashboard',
        why: 'front desk has no finance access',
    },
    {
        email: 'clean@email.com',
        password: '11111111',
        path: '/staff/kitchen',
        why: 'housekeeping cannot see kitchen orders',
    },
    {
        email: 'account@email.com',
        password: '11111111',
        path: '/frontdesk/dashboard',
        why: 'accounting has no front-desk access',
    },
    {
        email: 'hr@email.com',
        password: '11111111',
        path: '/admin/reports/occupancy',
        why: 'HR is not part of the admin reporting surface',
    },
];

test.describe('authorization', () => {
    for (const entry of DENIED) {
        test(`${entry.email} is refused ${entry.path}`, async ({ page }) => {
            await login(page, entry.email, entry.password);

            const response = await page.goto(entry.path);
            const status = response?.status() ?? 0;

            // 403 is the expected answer. A redirect away also counts as denied
            // (some layouts route an unauthorised user to their own dashboard),
            // but a 200 means the page was served and the middleware is missing.
            const redirected = !new URL(page.url()).pathname.startsWith(entry.path);
            expect(
                status === 403 || redirected,
                `${entry.email} got ${status} for ${entry.path} (${entry.why}); url=${page.url()}`,
            ).toBe(true);
        });
    }
});
