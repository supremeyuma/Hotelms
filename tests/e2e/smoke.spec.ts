import { test, expect } from '@playwright/test';

/**
 * Harness smoke test: proves the app-under-test boots and the public surface
 * renders. It intentionally asserts on the real home page rather than the /up
 * health route (which the webServer already uses as its readiness probe).
 */
test('public home renders', async ({ page }) => {
    const response = await page.goto('/');

    expect(response, 'navigation to / returned a response').not.toBeNull();
    expect(response!.status()).toBe(200);

    // routes/public.php -> PublicController@home renders Pages/Public/Home.vue,
    // which is wrapped in PublicLayout. Assert the Inertia page component and a
    // stable piece of chrome that is present regardless of seeded content.
    await expect(page.locator('[data-page]')).toHaveCount(1);
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 15_000 });
});
