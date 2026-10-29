/**
 * Web e2e — every GET page the administrator surface exposes.
 *
 * The workflow-by-workflow specs prove the flows we thought to write. This one
 * is the safety net for the ones nobody wrote: it walks the application's own
 * route table and opens every parameterless GET page as a signed-in manager,
 * failing on a 5xx or on a Laravel exception written to the log.
 *
 * It deliberately does not assert page content. A screen can be unpolished and
 * still be a release blocker only if it throws; content assertions belong to
 * the focused specs (auth, public site, booking, ...).
 */
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { expect, test } from '@playwright/test';
import { login } from './support/app';

const REPO_ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const LARAVEL_LOG = resolve(REPO_ROOT, 'storage', 'logs', 'laravel.log');

interface RouteRow {
    method: string;
    uri: string;
    name: string | null;
    middleware: string[];
}

/**
 * The parameterless GET pages under the given prefixes.
 *
 * Routes with `{parameters}` cannot be opened without inventing an id, and a
 * missing id proves nothing about the page. Anything outside the admin/finance/
 * HR surface is covered by its own spec.
 */
function getRoutes(prefixes: string[]): string[] {
    const raw = execFileSync(
        'php',
        ['artisan', 'route:list', '--json'],
        { encoding: 'utf8', cwd: REPO_ROOT, timeout: 120_000 },
    );

    const routes = JSON.parse(raw) as RouteRow[];

    return Array.from(
        new Set(
            routes
                .filter((route) => route.method.includes('GET'))
                .map((route) => route.uri.replace(/^\//, ''))
                .filter((uri) => !uri.includes('{'))
                .filter((uri) =>
                    prefixes.some((prefix) => uri === prefix || uri.startsWith(`${prefix}/`)),
                ),
        ),
    ).sort();
}

const ADMIN_PREFIXES = ['admin', 'reports', 'finance', 'hr'];

test.describe('administrator surface', () => {
    test('every parameterless admin GET page renders without a server error', async ({
        page,
    }) => {
        const routes = getRoutes(ADMIN_PREFIXES);
        expect(routes.length).toBeGreaterThan(20);

        await login(page, 'manager@email.com', 'ManagerPass1!');

        const logBefore = readFileSync(LARAVEL_LOG, 'utf8').length;
        const failures: Array<{ route: string; status: number }> = [];

        for (const route of routes) {
            const response = await page.goto(`/${route}`, { waitUntil: 'domcontentloaded' });

            const status = response?.status() ?? 0;
            // 5xx is the release blocker. 403/404 mean "not this manager's page
            // or no such record", both of which are deliberate answers.
            if (status >= 500) {
                failures.push({ route, status });
            }
        }

        const newLog = readFileSync(LARAVEL_LOG, 'utf8').slice(logBefore);
        const exceptions = newLog
            .split(/\r?\n/)
            .filter((line) => line.includes('.ERROR:'))
            .slice(0, 5);

        expect(failures, `pages answered 5xx:\n${JSON.stringify(failures, null, 2)}`).toEqual([]);
        expect(exceptions, `new exceptions in laravel.log:\n${exceptions.join('\n')}`).toEqual([]);
    });
});
