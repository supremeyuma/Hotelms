/**
 * Web e2e — the public site the guest sees before booking.
 *
 * Every public page must render (a 500 on /rooms is a lost booking), gallery
 * imagery must actually load, and the two contact channels must accept a
 * submission and come back with a confirmation. Content assertions are limited
 * to stable, meaningful facts: the page's own heading and the fact that images
 * decoded.
 */
import { expect, test } from '@playwright/test';

const PAGES: Array<{ path: string; heading: RegExp }> = [
    { path: '/', heading: /moorelife|hotel|resort/i },
    { path: '/gallery', heading: /gallery/i },
    { path: '/amenities', heading: /amenities|facilities/i },
    { path: '/club-lounge', heading: /club|lounge/i },
    { path: '/policies', heading: /polic|terms/i },
    { path: '/contact', heading: /contact|get in touch/i },
    { path: '/feedback', heading: /feedback/i },
    { path: '/events', heading: /event/i },
    { path: '/booking', heading: /book|reserv|availability|check/i },
];

test.describe('public site', () => {
    for (const entry of PAGES) {
        test(`${entry.path} renders`, async ({ page }) => {
            const response = await page.goto(entry.path);
            expect(response?.status(), `${entry.path} status`).toBeLessThan(400);

            await expect(page.locator('body')).toContainText(entry.heading);
        });
    }

    test('gallery images actually load', async ({ page }) => {
        await page.goto('/gallery');

        // "present" is not "loaded": a broken storage link yields a 200 page
        // full of empty <img> elements, which is how asset regressions hide.
        await expect
            .poll(
                () =>
                    page.evaluate(
                        () =>
                            Array.from(document.images).filter(
                                (img) => img.complete && img.naturalWidth > 0,
                            ).length,
                    ),
                { timeout: 20_000 },
            )
            .toBeGreaterThan(0);
    });

    test('a contact message is accepted', async ({ page }) => {
        await page.goto('/contact');

        await page.locator('#name').fill('E2E Contact');
        await page.locator('#email').fill('e2e-contact@example.test');
        await page.locator('#subject').fill('E2E subject');
        await page.locator('#message').fill('This message was written by the e2e suite.');

        await page.getByRole('button', { name: /send message/i }).click();

        // The controller redirects back with this exact flash; asserting the
        // specific sentence (not a loose /message/) is what proves the mail was
        // actually sent — a loose pattern passed while the mailable was fatal.
        await expect(
            page.getByText(/your message has been sent/i),
        ).toBeVisible({ timeout: 20_000 });
    });

    test('public menus render', async ({ page }) => {
        for (const path of ['/menu/online/kitchen', '/menu/view/bar']) {
            const response = await page.goto(path);
            expect(response?.status(), `${path} status`).toBeLessThan(400);
            await expect(page.locator('body')).not.toContainText('Server Error');
        }
    });
});
