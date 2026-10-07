import { expect, test } from './compadres-test.js';

test('fictional development brands and products render from structured data', async ({ page }) => {
  await page.goto('/brands/');
  await expect(page.getByRole('heading', { level: 1, name: 'Cigar Brands' })).toBeVisible();
  const brandLink = page.getByRole('link', { name: /Ember Quay/i }).first();
  await expect(brandLink).toBeVisible();
  await expect(brandLink).toHaveAttribute('href', /\/brands\/ember-quay\/$/);
  await page.goto('/brands/ember-quay/');
  await expect(page.getByRole('heading', { level: 1, name: /Ember Quay/i })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Cigars from this brand' })).toBeVisible();
});

test('the shop does not show the filter bar, result count, or sort dropdown', async ({ page }) => {
  await page.goto('/shop/');
  await expect(page.getByRole('heading', { level: 1, name: 'Shop' })).toBeVisible();
  await expect(page.getByText('Filter cigars', { exact: true })).toHaveCount(0);
  await expect(page.getByLabel('Shop order')).toHaveCount(0);
  await expect(page.locator('.woocommerce-result-count')).toHaveCount(0);
});

test('structured cigar metadata displays without internal classifications', async ({ page }) => {
  await page.goto('/?s=DEV-FICTIONAL-ROBUSTO-SINGLE');
  const product = page.getByRole('link', { name: /Ember Quay Fictional Robusto Single/i }).first();
  await product.click();
  await expect(page.getByRole('tab', { name: 'Cigar specifications' })).toBeVisible();
  await page.getByRole('tab', { name: 'Cigar specifications' }).click();
  await expect(page.getByText('Connecticut')).toBeVisible();
  await expect(page.getByText('development-only')).toHaveCount(0);
});

test('a fictional simple product can be added to the shared cart', async ({ page }) => {
  await page.goto('/?s=DEV-FICTIONAL-ROBUSTO-SINGLE');
  await page.getByRole('link', { name: /Ember Quay Fictional Robusto Single/i }).first().click();
  await page.getByRole('button', { name: 'Add to cart', exact: true }).click();
  await expect(page.getByText(/has been added to your cart/i)).toBeVisible();
  await page.goto('/cart/');
  await expect(page.getByText(/Ember Quay Fictional Robusto Single/i).first()).toBeVisible();
});
