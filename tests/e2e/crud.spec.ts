/**
 * Web e2e — create/update/delete round-trips for the records an operator owns.
 *
 * Read-only page checks cannot see a create that silently drops a field, an
 * update that never persists, or a delete that leaves the row. Each case here
 * writes through the real form and asserts the database.
 */
import { expect, test } from '@playwright/test';
import { login } from './support/app';
import { sqliteCount, sqliteScalar } from './support/db';

test.describe('crud round-trips', () => {
    test.setTimeout(120_000);

    test('a room is created and deleted', async ({ page }) => {
        const stamp = Date.now();
        const name = `E2E Room ${stamp}`;
        const code = `E2E${stamp}`;

        page.on('dialog', (dialog) => void dialog.accept());

        await login(page, 'manager@email.com', 'ManagerPass1!');
        await page.goto('/admin/rooms/create');

        await page.getByPlaceholder('Amber 201').fill(name);
        await page.getByPlaceholder('AMB201').fill(code);
        await page.locator('input[type="number"]').first().fill('1');
        await page.getByRole('button', { name: /create room/i }).click();

        await expect
            .poll(() => sqliteCount(`SELECT count(*) FROM rooms WHERE code = '${code}'`), {
                timeout: 20_000,
            })
            .toBe(1);

        // Delete it again from the index (rooms render as cards, not table rows).
        // The index supports a search, so the new room is found regardless of
        // how the list is sorted or paginated.
        await page.goto(`/admin/rooms?search=${encodeURIComponent(code)}`);
        const card = page.locator('article', { hasText: name }).first();
        await expect(card).toBeVisible({ timeout: 20_000 });
        await card.getByRole('button', { name: /^delete$/i }).click();

        await expect
            .poll(
                () =>
                    sqliteCount(
                        `SELECT count(*) FROM rooms WHERE code = '${code}' AND deleted_at IS NOT NULL`,
                    ),
                { timeout: 20_000 },
            )
            .toBe(1);
    });

    test('a menu item availability toggle persists', async ({ page }) => {
        await login(page, 'kitchen@email.com', '11111111');
        await page.goto('/staff/kitchen/menu');

        const item = sqliteScalar(
            "SELECT id FROM menu_items WHERE service_area = 'kitchen' ORDER BY id LIMIT 1",
        );
        const itemName = sqliteScalar(`SELECT name FROM menu_items WHERE id = ${item}`);
        expect(item, 'the seed must carry a kitchen item').not.toBe('');

        const before = sqliteScalar(`SELECT is_available FROM menu_items WHERE id = ${item}`);

        // Items live inside category and subcategory panels that start
        // collapsed; expand every panel header (they are the buttons holding the
        // ▼ chevron) so the item's own checkbox is reachable.
        const panelToggles = page.getByRole('button', { name: /▼/ });
        const panels = await panelToggles.count();
        for (let i = 0; i < panels; i += 1) {
            await panelToggles.nth(i).click();
        }

        const row = page
            .locator('div.row')
            .filter({ hasText: itemName })
            .filter({ visible: true })
            .first();
        await expect(row).toBeVisible({ timeout: 20_000 });

        await row.locator('input[type="checkbox"]').click();

        await expect
            .poll(
                () => sqliteScalar(`SELECT is_available FROM menu_items WHERE id = ${item}`),
                { timeout: 20_000 },
            )
            .not.toBe(before);

        // Restore so later specs see the seeded catalogue.
        await row.locator('input[type="checkbox"]').click();
        await expect
            .poll(
                () => sqliteScalar(`SELECT is_available FROM menu_items WHERE id = ${item}`),
                { timeout: 20_000 },
            )
            .toBe(before);
    });

    test('a staff account is created with its role', async ({ page }) => {
        const stamp = Date.now();
        const email = `e2e-staff-${stamp}@example.test`;

        await login(page, 'manager@email.com', 'ManagerPass1!');
        await page.goto('/admin/staff/create');

        await page.locator('#name').fill(`E2E Staff ${stamp}`);
        await page.locator('#email').fill(email);
        await page.locator('#position').fill('Front Desk');
        await page.locator('#password').fill('E2e-Staff-Passw0rd!');

        // The role select is required; choose the first real option.
        const roleSelect = page.locator('#role');
        const roleOptions = await roleSelect.locator('option').count();
        expect(roleOptions, 'the staff form must offer roles').toBeGreaterThan(1);
        await roleSelect.selectOption({ index: 1 });

        await page.getByRole('button', { name: /create staff/i }).click();

        await expect
            .poll(() => sqliteCount(`SELECT count(*) FROM users WHERE email = '${email}'`), {
                timeout: 20_000,
            })
            .toBe(1);

        expect(
            sqliteCount(
                `SELECT count(*) FROM model_has_roles m JOIN users u ON u.id = m.model_id WHERE u.email = '${email}'`,
            ),
            'the new account must carry a role',
        ).toBeGreaterThan(0);
    });
});
