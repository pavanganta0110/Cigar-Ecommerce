import { expect, test as base } from '@playwright/test';

export const test = base.extend<{ acceptAgeGate: void }>({
  acceptAgeGate: [
    async ({ page }, use) => {
      await page.goto('/');
      const dialog = page.getByRole('dialog', { name: /21 or older/i });
      if (await dialog.isVisible()) {
        await page.getByRole('button', { name: /I am 21 or older/i }).click();
        await expect(dialog).toBeHidden();
      }
      await use();
    },
    { auto: true },
  ],
});

export { expect };
