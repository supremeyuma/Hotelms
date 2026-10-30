/**
 * Club POS helpers shared by the sale spec and the lifecycle spec.
 */
import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

import { sqliteCount, sqliteScalar } from './db';

/**
 * Enter the club PIN on the till's keypad.
 *
 * Seeded club profiles use `111111`. Returns false when the till was already
 * unlocked, so callers can log in and continue either way.
 */
export async function unlockTill(page: Page): Promise<boolean> {
    const pinDialog = page.getByRole('dialog').filter({ hasText: /enter your pin/i });
    if (!(await pinDialog.isVisible().catch(() => false))) {
        return false;
    }

    for (const digit of ['1', '1', '1', '1', '1', '1']) {
        await pinDialog.getByRole('button', { name: digit, exact: true }).click();
    }

    const [response] = await Promise.all([
        page
            .waitForResponse((res) => res.url().includes('/pos/staff/switch'), {
                timeout: 15_000,
            })
            .catch(() => null),
        pinDialog.getByRole('button', { name: /^enter$/i }).click(),
    ]);

    expect(response?.status() ?? 0, 'the PIN unlock must be accepted').toBe(200);
    await expect(pinDialog).toBeHidden({ timeout: 20_000 });
    return true;
}

/** Open a fresh docket and return its id (a ULID). */
export async function openDocket(page: Page): Promise<string> {
    const before = sqliteCount('SELECT count(*) FROM pos_dockets');

    await page.getByRole('button', { name: /new docket/i }).click();

    await expect
        .poll(() => sqliteCount('SELECT count(*) FROM pos_dockets'), { timeout: 20_000 })
        .toBe(before + 1);

    return sqliteScalar('SELECT id FROM pos_dockets ORDER BY id DESC LIMIT 1');
}

/** Add the first available product to the open docket. */
export async function addFirstProduct(page: Page): Promise<void> {
    const product = page.getByRole('button', { name: /Ksh \d/ }).first();
    await expect(product).toBeVisible({ timeout: 20_000 });
    await product.click();
}

/** Take cash for the open docket, covering its full total. */
export async function payDocket(page: Page, docketId: string): Promise<void> {
    const key = docketId.replace(/'/g, "''");
    const total = Number(sqliteScalar(`SELECT total FROM pos_dockets WHERE id = '${key}'`));
    expect(total, 'the docket must carry a total').toBeGreaterThan(0);

    await page.getByRole('button', { name: 'Pay', exact: true }).click();

    const payment = page.getByRole('dialog');
    await expect(payment).toBeVisible({ timeout: 10_000 });
    await payment.locator('#pmt-amount-0').fill(String(total));
    await payment.getByRole('button', { name: /complete payment/i }).click();

    await expect
        .poll(() => sqliteScalar(`SELECT status FROM pos_dockets WHERE id = '${key}'`), {
            timeout: 25_000,
        })
        .toMatch(/^(paid|closed)$/);
}
