import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

let originalAgeGate: string | null | undefined;

function wpCli(args: string[]): string {
  return execFileSync('docker', ['compose', 'run', '--rm', 'wpcli', ...args], {
    cwd: process.cwd(),
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}

function optionExists(name: string): boolean {
  const matches = JSON.parse(
    wpCli(['option', 'list', `--search=${name}`, '--field=option_name', '--format=json']),
  ) as string[];
  return matches.includes(name);
}

function getOption(name: string): string | null {
  return optionExists(name) ? wpCli(['option', 'get', name, '--format=json']) : null;
}

function restoreOption(name: string, value: string | null): void {
  if (value === null) {
    if (optionExists(name)) {
      wpCli(['option', 'delete', name]);
    }
    expect(optionExists(name)).toBeFalsy();
    return;
  }
  wpCli(['option', 'update', name, value, '--format=json']);
}

test.beforeAll(() => {
  originalAgeGate = getOption('compadres_age_gate');
  const settings =
    originalAgeGate === null ? {} : (JSON.parse(originalAgeGate) as Record<string, unknown>);
  wpCli([
    'option',
    'update',
    'compadres_age_gate',
    JSON.stringify({ ...settings, enabled: true }),
    '--format=json',
  ]);
});

test.afterAll(() => {
  if (originalAgeGate !== undefined) {
    restoreOption('compadres_age_gate', originalAgeGate);
  }
});

test.beforeEach(async ({ context }) => {
  await context.clearCookies();
});

test('first visit presents an accessible modal with contained keyboard behavior @a11y', async ({
  page,
}) => {
  await page.goto('/');

  const dialog = page.locator('[data-age-gate] [role="dialog"]');
  const confirm = page.locator('[data-age-confirm]');
  const exit = page.locator('[data-age-exit]');

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
  const dialog = page.locator('[data-age-gate] [role="dialog"]');
  await page.locator('[data-age-confirm]').click();

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
  const exit = page.locator('[data-age-exit]');
  await expect(exit).toBeVisible();
  await expect(exit).toHaveAttribute('href', 'https://www.google.com/');
});
