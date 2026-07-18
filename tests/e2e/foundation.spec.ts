import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('homepage renders the Compadres storefront foundation', async ({ page }) => {
  await page.goto('/');

  await expect(page).toHaveTitle(/Compadres Cigars/i);
  await expect(
    page.getByRole('heading', { level: 1, name: /shared table/i }),
  ).toBeVisible();
  await expect(page.getByRole('link', { name: /shop all cigars/i }).first()).toBeVisible();
});

test('WooCommerce shop and account routes are available', async ({ page }) => {
  await page.goto('/shop/');
  await expect(page.getByRole('main')).toBeVisible();

  await page.goto('/my-account/');
  await expect(page.getByRole('main')).toBeVisible();
});

test('homepage has no automatically detectable accessibility violations @a11y', async ({
  page,
}) => {
  await page.goto('/');
  const results = await new AxeBuilder({ page }).analyze();

  expect(results.violations).toEqual([]);
});
