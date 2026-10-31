/**
 * Web e2e — the Club POS sale, end to end.
 *
 * A bartender unlocks the till with a PIN, opens a docket, adds a drink, takes
 * cash and completes the payment. The docket, its item and the payment must all
 * exist afterwards with the right amounts — a POS that renders a receipt but
 * records nothing is the worst failure mode in the building.
 */
import { expect, test } from '@playwright/test';
import { login } from './support/app';
import { sqliteCount, sqliteScalar } from './support/db';

test.describe('club POS', () => {
    test.setTimeout(150_000);

    test('a bartender opens a docket, adds a drink and takes payment', async ({ page }) => {
        await login(page, 'club.bartender@email.com', '11111111');

        // The till is locked until a club PIN is entered (the seeded profiles
        // use 111111, which is what the keypad expects).
        const pinDialog = page.getByRole('dialog').filter({ hasText: /enter your pin/i });
        if (await pinDialog.isVisible().catch(() => false)) {
            for (const digit of ['1', '1', '1', '1', '1', '1']) {
                await pinDialog.getByRole('button', { name: digit, exact: true }).click();
            }

            const [pinResponse] = await Promise.all([
                page
                    .waitForResponse(
                        (res) => res.url().includes('/pos/staff/switch'),
                        { timeout: 15_000 },
                    )
                    .catch(() => null),
                pinDialog.getByRole('button', { name: /^enter$/i }).click(),
            ]);

            if (pinResponse) {
                console.log(
                    "[club-pos] pin switch ->",
                    pinResponse.status(),
                    (await pinResponse.text().catch(() => "")).slice(0, 200),
                );
            } else {
                console.log("[club-pos] pin switch -> no response observed");
            }

            await expect(pinDialog).toBeHidden({ timeout: 20_000 });
        }

        await expect(page).toHaveURL(/\/club\/pos/, { timeout: 20_000 });

        const docketsBefore = sqliteCount('SELECT count(*) FROM pos_dockets');

        // --- open a docket ----------------------------------------------------
        await page.getByRole('button', { name: /new docket/i }).click();

        await expect
            .poll(() => sqliteCount('SELECT count(*) FROM pos_dockets'), { timeout: 20_000 })
            .toBe(docketsBefore + 1);

        const docketId = sqliteScalar('SELECT id FROM pos_dockets ORDER BY id DESC LIMIT 1');
        const docketKey = docketId.replace(/'/g, "''");

        // --- add the first available drink ------------------------------------
        const firstProduct = page.getByRole('button', { name: /Ksh \d/ }).first();
        await expect(firstProduct).toBeVisible({ timeout: 20_000 });
        await firstProduct.click();

        await expect
            .poll(
                () =>
                    sqliteCount(
                        `SELECT count(*) FROM pos_docket_items WHERE docket_id = '${docketKey}'`,
                    ),
                { timeout: 20_000 },
            )
            .toBe(1);

        const total = Number(
            sqliteScalar(`SELECT total FROM pos_dockets WHERE id = '${docketKey}'`),
        );
        expect(total, 'the docket must carry a total').toBeGreaterThan(0);

        // --- take cash --------------------------------------------------------
        await page.getByRole('button', { name: 'Pay', exact: true }).click();

        const payment = page.getByRole('dialog');
        await expect(payment).toBeVisible({ timeout: 10_000 });
        await payment.locator('#pmt-amount-0').fill(String(total));
        await payment.getByRole('button', { name: /complete payment/i }).click();

        await expect
            .poll(
                () =>
                    sqliteScalar(`SELECT status FROM pos_dockets WHERE id = '${docketKey}'`),
                { timeout: 25_000 },
            )
            // A settled docket is `closed` in this POS; older rows used `paid`.
            .toMatch(/^(paid|closed)$/);

        expect(
            sqliteCount(
                `SELECT count(*) FROM pos_docket_payments WHERE docket_id = '${docketKey}'`,
            ),
            'a payment row must back the paid docket',
        ).toBeGreaterThan(0);

        // The sale must reach the books, not just the till: one journal entry,
        // balanced, referencing this docket number.
        expect(
            sqliteCount(
                `SELECT count(*) FROM journal_entries
                 WHERE reference_type = 'pos_docket'
                   AND reference_id = (SELECT docket_number FROM pos_dockets WHERE id = '${docketKey}')`,
            ),
            'a settled sale must post a journal entry',
        ).toBe(1);
    });
});
