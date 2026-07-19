import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test.beforeEach(async ({ context }) => {
  await context.clearCookies();
});

test('first visit presents an accessible modal with contained keyboard behavior @a11y', async ({
  page,
}) => {
  await page.goto('/');

  const dialog = page.getByRole('dialog', { name: /21 or older/i });
  const confirm = page.getByRole('button', { name: /I am 21 or older/i });
  const exit = page.getByRole('link', { name: /Exit website/i });

  await expect(dialog).toBeVisible();
  await expect(dialog).toHaveAttribute('aria-modal', 'true');
  await expect(page.getByText(/does not perform identity verification/i)).toBeVisible();
  await expect(confirm).toBeFocused();

  await page.keyboard.press('Shift+Tab');
  await expect(exit).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(confirm).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(dialog).toBeVisible();
  await expect(confirm).toBeFocused();
  await expect(dialog.getByRole('status')).toContainText(/confirm your age or use the exit link/i);

  const results = await new AxeBuilder({ page }).analyze();
  expect(results.violations).toEqual([]);
});

test('confirmation is persisted in a secure server-issued cookie and restores page focus', async ({
  context,
  page,
}) => {
  await page.goto('/');
  const dialog = page.getByRole('dialog', { name: /21 or older/i });
  await page.getByRole('button', { name: /I am 21 or older/i }).click();

  await expect(dialog).toBeHidden();
  await expect(page.getByRole('main')).toBeFocused();
  const cookie = (await context.cookies()).find(({ name }) => name === 'compadres_age_confirmed');
  expect(cookie).toMatchObject({ httpOnly: true, sameSite: 'Lax' });
  expect(cookie?.expires).toBeGreaterThan(Date.now() / 1000);

  await page.reload();
  await expect(page.getByRole('dialog')).toHaveCount(0);
});

test('exit action is visible and uses the configured URL', async ({ page }) => {
  await page.goto('/');
  const exit = page.getByRole('link', { name: /Exit website/i });
  await expect(exit).toBeVisible();
  await expect(exit).toHaveAttribute('href', 'https://www.google.com/');
});
