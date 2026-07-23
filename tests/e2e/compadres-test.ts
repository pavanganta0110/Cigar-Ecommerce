import { expect, test as base } from '@playwright/test';

export const test = base.extend<{ acceptAgeGate: void }>({
  acceptAgeGate: [
    async ({ page }, use) => {
      await page.goto('/');
      const dialog = page.locator('[data-age-gate] [role="dialog"]');
      if (await dialog.isVisible()) {
        await page.locator('[data-age-confirm]').click();
        await expect(dialog).toBeHidden();
      }
      await use();
    },
    { auto: true },
  ],
});

export { expect };
