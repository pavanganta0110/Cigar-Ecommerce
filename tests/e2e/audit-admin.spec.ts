import { execFileSync } from 'node:child_process';
import { expect, test, type Page } from '@playwright/test';

const password = 'CompadresTestOnly42';
let originalAgeGate: string | null = null;

function wpCli(args: string[]): string {
  return execFileSync('docker', ['compose', 'run', '--rm', 'wpcli', ...args], {
    cwd: process.cwd(),
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}

function ensureUser(username: string, role: string): void {
  try {
    wpCli(['user', 'get', username, '--field=ID']);
  } catch {
    wpCli(['user', 'create', username, `${username}@example.test`, '--role=subscriber', '--porcelain']);
  }
  wpCli(['user', 'update', username, `--role=${role}`, `--user_pass=${password}`]);
}

async function login(page: Page, username: string): Promise<void> {
  await page.goto('/wp-login.php');
  const response = await page.request.post('/wp-login.php', {
    form: {
      log: username,
      pwd: password,
      'wp-submit': 'Log In',
      redirect_to: 'http://localhost:8080/wp-admin/',
      testcookie: '1',
    },
  });
  expect(response.ok()).toBeTruthy();
  await page.goto('/wp-admin/');
}

test.beforeAll(() => {
  wpCli(['eval', '\\Compadres\\Commerce\\Security\\RoleManager::install(); update_option("compadres_roles_version", "2", false);']);
  ensureUser('audit-store-admin', 'compadres_store_administrator');
  ensureUser('audit-operations', 'compadres_operations_manager');
  ensureUser('audit-unauthorized', 'subscriber');
  try {
    originalAgeGate = wpCli(['option', 'get', 'compadres_age_gate', '--format=json']);
  } catch {
    originalAgeGate = null;
  }
  wpCli([
    'eval',
    '\\Compadres\\Commerce\\Audit\\AuditServiceFactory::create()->entityChange("audit.browser_security", "order", "<img src=x onerror=alert(1)>", array("title"=>"Before"), array("title"=>"<script>alert(1)</script>"), 0);',
  ]);
});

test.afterAll(() => {
  if ( originalAgeGate ) {
    wpCli(['option', 'update', 'compadres_age_gate', originalAgeGate, '--format=json']);
  } else {
    wpCli(['option', 'delete', 'compadres_age_gate']);
  }
});

test('store administrators can filter and safely inspect audit records', async ({ page }) => {
  await login(page, 'audit-store-admin');
  await page.goto('/wp-admin/admin.php?page=compadres-compliance');
  await expect(page.getByRole('heading', { name: 'Compadres Compliance' })).toBeVisible();
  await page.getByLabel('Title').fill('Adults 21+ — test configuration');
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.locator('#setting-error-settings_updated')).toContainText('Settings saved');
  await page.goto('/wp-admin/admin.php?page=compadres-audit-log&event_type=compliance.age_gate.settings_updated');
  await expect(page.getByRole('cell', { name: 'compliance.age_gate.settings_updated' }).first()).toBeVisible();
  await page.goto('/wp-admin/admin.php?page=compadres-audit-log&event_type=audit.browser_security');

  await expect(page.getByRole('heading', { name: 'Compadres Audit Log' })).toBeVisible();
  await expect(page.locator('td').filter({ hasText: '<img src=x onerror=alert(1)>' }).first()).toBeVisible();
  await expect(page.locator('script').filter({ hasText: 'alert(1)' })).toHaveCount(0);
  await page.getByLabel('Event type').fill('<script>alert(2)</script>');
  await page.getByLabel('Event type').evaluate((field: HTMLInputElement) => field.form?.submit());
  await page.waitForLoadState('domcontentloaded');
  await expect(page.getByLabel('Event type')).toHaveValue('');
  await expect(page.locator('script').filter({ hasText: 'alert(2)' })).toHaveCount(0);
});

test('operations managers can view audit records but do not receive export capability', async ({ page }) => {
  await login(page, 'audit-operations');
  await page.goto('/wp-admin/admin.php?page=compadres-audit-log');

  await expect(page.getByRole('heading', { name: 'Compadres Audit Log' })).toBeVisible();
  expect(
    wpCli([
      'eval',
      '$user=get_user_by("login", "audit-operations"); echo user_can($user, "compadres_export_audit_logs") ? "yes" : "no";',
    ]),
  ).toBe('no');
});

test('unauthorized roles are rejected on direct audit URLs', async ({ page }) => {
  await login(page, 'audit-unauthorized');
  const response = await page.goto('/wp-admin/admin.php?page=compadres-audit-log');

  expect(response?.status()).toBe(403);
  await expect(page.getByRole('heading', { name: 'Compadres Audit Log' })).toHaveCount(0);
  await expect(page.locator('body')).toContainText(/not allowed|permission/i);

  const settingsResponse = await page.goto('/wp-admin/admin.php?page=compadres-compliance');
  expect(settingsResponse?.status()).toBe(403);
  await expect(page.locator('body')).toContainText(/not allowed|permission/i);
});
