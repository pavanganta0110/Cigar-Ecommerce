import { expect, test } from './compadres-test.js';

test('fictional development brands and products render from structured data', async ({ page }) => {
  await page.goto('/brands/');
  await expect(page.getByRole('heading', { level: 1, name: 'Cigar Brands' })).toBeVisible();
  const brandLink = page.getByRole('link', { name: /Ember Quay/i }).first();
  await expect(brandLink).toBeVisible();
  await brandLink.click();
  await expect(page.getByRole('heading', { level: 1, name: /Ember Quay/i })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Cigars from this brand' })).toBeVisible();
});

test('catalog filters use labeled query-string controls', async ({ page }) => {
  await page.goto('/shop/');
  const filters = page.getByText('Filter cigars', { exact: true });
  await expect(filters).toBeVisible();
  await page.getByLabel('Strength').selectOption('medium');
  await page.getByRole('button', { name: 'Apply filters' }).click();
  await expect(page).toHaveURL(/strength=medium/);
  await expect(page.getByRole('link', { name: /Fictional/i }).first()).toBeVisible();
  await expect(page.getByRole('link', { name: 'Clear all' })).toBeVisible();
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
