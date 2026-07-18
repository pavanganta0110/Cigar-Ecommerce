import { expect, test } from '@playwright/test';

test('core content pages use the Compadres theme', async ({ page }) => {
  for (const route of ['/about/', '/contact/', '/shipping-policy/', '/age-policy/']) {
    const response = await page.goto(route);
    expect(response?.status(), route).toBe(200);
    await expect(page.locator('body')).toHaveClass(/compadres-site/);
    await expect(page.getByRole('main')).toBeVisible();
  }
});

test('search results expose a labeled search form', async ({ page }) => {
  await page.goto('/?s=fictional');

  await expect(page.getByRole('heading', { name: /search results/i })).toBeVisible();
  await expect(page.getByRole('searchbox', { name: /search/i })).toBeVisible();
});

test('404 page provides recovery navigation', async ({ page }) => {
  const response = await page.goto('/this-route-does-not-exist/');

  expect(response?.status()).toBe(404);
  await expect(page.getByRole('heading', { name: /not found/i })).toBeVisible();
  await expect(page.getByRole('link', { name: /return home/i })).toBeVisible();
});

test('mobile navigation opens, closes with Escape, and returns focus', async ({
  page,
}, testInfo) => {
  test.skip(!testInfo.project.name.includes('mobile'), 'Mobile-only behavior');
  await page.goto('/');

  const toggle = page.getByRole('button', { name: /toggle navigation/i });
  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(page.getByRole('navigation', { name: /primary navigation/i })).toBeVisible();

  await page.keyboard.press('Escape');
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(toggle).toBeFocused();
});

test('homepage exposes data-driven storefront sections with safe empty states', async ({
  page,
}) => {
  await page.goto('/');

  for (const heading of [
    'Featured brands',
    'Featured cigars',
    'Best sellers',
    'New releases',
    'Samplers',
  ]) {
    await expect(page.getByRole('heading', { name: heading })).toBeVisible();
  }
  await expect(page.getByText(/catalog content is being prepared/i).first()).toBeVisible();
  await expect(page.getByText(/site entry confirmation does not replace/i).first()).toBeVisible();
});

test('store, policy, cart, checkout, and account routes render', async ({ page }) => {
  for (const route of [
    '/privacy-policy/',
    '/returns-policy/',
    '/terms/',
    '/restrictions/',
    '/shop/',
    '/cart/',
    '/checkout/',
    '/my-account/',
  ]) {
    const response = await page.goto(route);
    expect(response?.status(), route).toBe(200);
    await expect(page.getByRole('main')).toBeVisible();
  }
});

test('fallback navigation exposes the current page', async ({ page }) => {
  await page.goto('/about/');

  await expect(page.locator('#primary-navigation a', { hasText: 'About' })).toHaveAttribute(
    'aria-current',
    'page',
  );
});
