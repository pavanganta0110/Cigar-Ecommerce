import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

const password = 'CompadresTestOnly42';
const genericMessage =
  'We cannot ship the current cart to this destination. Please update the shipping address or remove the restricted item.';
let originalVerification: string | null = null;
let originalCod: string | null = null;
let originalAgeGate: string | null = null;
let originalCheckoutContent = '';
let restrictedProductId = '';
let allowedProductId = '';
let originalProductStates: Record<string, string> = {};
const ruleIds: string[] = [];

function wpCli(args: string[], environment: Record<string, string> = {}): string {
  return execFileSync(
    'docker',
    [
      'compose',
      'run',
      '--rm',
      ...Object.entries(environment).flatMap(([name, value]) => ['-e', `${name}=${value}`]),
      'wpcli',
      ...args,
    ],
    { cwd: process.cwd(), encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
  ).trim();
}

function getOption(name: string): string | null {
  try {
    return wpCli(['option', 'get', name, '--format=json']);
  } catch {
    return null;
  }
}

function restoreOption(name: string, value: string | null): void {
  if (value === null) {
    try {
      wpCli(['option', 'delete', name]);
    } catch {
      // The option is already absent.
    }
    return;
  }
  wpCli(['option', 'update', name, value, '--format=json']);
}

function ensureUser(username: string, role: string): void {
  try {
    wpCli(['user', 'get', username, '--field=ID']);
  } catch {
    wpCli(['user', 'create', username, `${username}@example.test`, '--role=subscriber', '--porcelain']);
  }
  wpCli(['user', 'update', username, `--role=${role}`, `--user_pass=${password}`]);
}

function createRule(overrides: Record<string, unknown>): string {
  const values = {
    name: 'Fictional E2E restriction',
    enabled: '1',
    priority: '100',
    country: 'US',
    state: '',
    city: '',
    postal_code: '',
    postal_prefix: '',
    product_id: '',
    category_id: '',
    brand_id: '',
    effective_at: '2020-01-01T00:00',
    expires_at: '',
    blocked_message: genericMessage,
    notes: 'E2E INTERNAL FICTIONAL NOTE',
    source_name: 'E2E Fictional',
    source_url: 'https://example.test/review',
    review_date: '2026-07-19',
    ...overrides,
  };
  const encoded = Buffer.from(JSON.stringify(values)).toString('base64');
  const id = wpCli([
    'eval',
    `$v=json_decode(base64_decode('${encoded}'),true);global $wpdb;` +
      '$i=\\Compadres\\Commerce\\Restrictions\\RestrictionRuleInput::fromArray($v,wp_timezone());' +
      '$r=new \\Compadres\\Commerce\\Restrictions\\WordPressRestrictionAdminRepository($wpdb);echo $r->create($i);',
  ]);
  ruleIds.push(id);
  return id;
}

function deleteRules(): void {
  const ids = ruleIds.map((id) => Number(id)).filter((id) => id > 0).join(',');
  wpCli([
    'eval',
    `global $wpdb;$ids=array(${ids});$extra=$wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}compadres_restriction_rules WHERE source_name=%s",'E2E Fictional'));foreach(array_unique(array_merge($ids,array_map('intval',$extra))) as $id){$wpdb->delete($wpdb->prefix.'compadres_restriction_targets',array('rule_id'=>$id),array('%d'));$wpdb->delete($wpdb->prefix.'compadres_restriction_rules',array('id'=>$id),array('%d'));}`,
  ]);
  ruleIds.length = 0;
}

async function login(page: Page, username: string): Promise<void> {
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
}

async function submitForm(page: Page, form: ReturnType<Page['locator']>): Promise<void> {
  await form.evaluate((element: HTMLFormElement) => element.requestSubmit());
  await page.waitForLoadState('domcontentloaded').catch(() => {});
}

async function openCheckout(page: Page, productIds: string[]): Promise<void> {
  for (const id of productIds) await page.goto(`/?add-to-cart=${id}`);
  await page.goto('/checkout/');
  await expect(page.getByRole('heading', { level: 1, name: 'Checkout' })).toBeVisible();
}

async function selectState(page: Page, state: string): Promise<void> {
  const field = page.locator('#billing_state');
  if ((await field.locator(`option[value="${state}"]`).count()) === 0) {
    await field.evaluate((select, value) => {
      const option = document.createElement('option');
      option.value = String(value);
      option.textContent = `Fictional ${String(value)}`;
      select.append(option);
    }, state);
  }
  await field.selectOption(state);
}

async function fillCheckout(page: Page, state: string, postalCode: string): Promise<void> {
  await page.locator('#billing_first_name').fill('Ada');
  await page.locator('#billing_last_name').fill('Lovelace');
  await page.locator('#billing_address_1').fill('1 Fictional Street');
  await page.locator('#billing_city').fill('Example City');
  await page.locator('#billing_postcode').fill(postalCode);
  await page.locator('#billing_phone').fill('3145550100');
  await page.locator('#billing_email').fill('restriction@example.test');
  if (await page.locator('#billing_country').isVisible()) {
    await page.locator('#billing_country').selectOption('US');
  }
  await selectState(page, state);
}

async function submitCheckout(page: Page): Promise<void> {
  await page.getByRole('button', { name: /place order/i }).click();
}

async function expectRestriction(page: Page): Promise<void> {
  const error = page.locator('.woocommerce-error, .wc-block-components-notice-banner.is-error');
  await expect(error).toContainText(genericMessage);
  await expect(page.getByText('E2E INTERNAL FICTIONAL NOTE')).toHaveCount(0);
  await expect(page.getByText('E2E Fictional', { exact: true })).toHaveCount(0);
}

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
  originalVerification = getOption('compadres_age_verification');
  originalCod = getOption('woocommerce_cod_settings');
  originalAgeGate = getOption('compadres_age_gate');
  originalCheckoutContent = wpCli([
    'eval',
    '$p=get_post((int)get_option("woocommerce_checkout_page_id"));echo $p ? $p->post_content : "";',
  ]);
  wpCli(['eval', '\\Compadres\\Commerce\\Restrictions\\RestrictionMigration::install();']);
  wpCli([
    'eval',
    '$id=(int)get_option("woocommerce_checkout_page_id");wp_update_post(array("ID"=>$id,"post_content"=>"[woocommerce_checkout]"));',
  ]);
  wpCli([
    'option',
    'update',
    'compadres_age_verification',
    JSON.stringify({
      enabled: true,
      provider: 'mock',
      requires_date_of_birth: false,
      hosted_url_template: '',
      production_approved: false,
      mock_status: 'passed',
      mock_refresh_status: 'passed',
    }),
    '--format=json',
  ]);
  wpCli(['option', 'update', 'compadres_age_gate', JSON.stringify({ enabled: false }), '--format=json']);
  wpCli([
    'option',
    'update',
    'woocommerce_cod_settings',
    JSON.stringify({
      enabled: 'yes',
      title: 'Cash on delivery',
      description: '',
      instructions: '',
      enable_for_methods: [],
      enable_for_virtual: 'yes',
    }),
    '--format=json',
  ]);
  restrictedProductId = wpCli([
    'eval',
    'echo wc_get_product_id_by_sku("DEV-FICTIONAL-ROBUSTO-SINGLE");',
  ]);
  allowedProductId = wpCli([
    'eval',
    'echo wc_get_product_id_by_sku("DEV-FICTIONAL-TORO-SINGLE");',
  ]);
  for (const id of [restrictedProductId, allowedProductId]) {
    originalProductStates[id] = wpCli([
      'eval',
      `$p=wc_get_product(${id});echo wp_json_encode(array('virtual'=>$p->is_virtual(),'manage_stock'=>$p->get_manage_stock(),'stock_quantity'=>$p->get_stock_quantity(),'stock_status'=>$p->get_stock_status()));`,
    ]);
    wpCli([
      'eval',
      `$p=wc_get_product(${id});$p->set_virtual(true);$p->set_manage_stock(false);$p->set_stock_status('instock');$p->save();`,
    ]);
  }
  createRule({ name: 'Fictional restricted state', state: 'ZZ' });
  createRule({ name: 'Fictional restricted postal code', postal_code: '00000' });
  createRule({
    name: 'Fictional destination product restriction',
    state: 'MO',
    product_id: restrictedProductId,
  });
  wpCli(['eval', '\\Compadres\\Commerce\\Security\\RoleManager::install();']);
  ensureUser('restriction-store-admin', 'compadres_store_administrator');
  ensureUser('restriction-compliance', 'subscriber');
  wpCli(['user', 'add-cap', 'restriction-compliance', 'compadres_manage_compliance']);
  ensureUser('restriction-unauthorized', 'subscriber');
});

test.afterAll(() => {
  try {
    wpCli(['compadres', 'restriction-fixtures', 'remove']);
  } catch {
    // Fixture is already absent.
  }
  deleteRules();
  restoreOption('compadres_age_verification', originalVerification);
  restoreOption('woocommerce_cod_settings', originalCod);
  restoreOption('compadres_age_gate', originalAgeGate);
  wpCli([
    'eval',
    `$id=(int)get_option('woocommerce_checkout_page_id');wp_update_post(array('ID'=>$id,'post_content'=>base64_decode('${Buffer.from(originalCheckoutContent).toString('base64')}')));`,
  ]);
  for (const [id, state] of Object.entries(originalProductStates)) {
    const encoded = Buffer.from(state).toString('base64');
    wpCli([
      'eval',
      `$s=json_decode(base64_decode('${encoded}'),true);$p=wc_get_product(${id});if($p&&is_array($s)){$p->set_virtual((bool)$s['virtual']);$p->set_manage_stock((bool)$s['manage_stock']);$p->set_stock_quantity($s['stock_quantity']);$p->set_stock_status((string)$s['stock_status']);$p->save();}`,
    ]);
  }
  wpCli(['user', 'remove-cap', 'restriction-compliance', 'compadres_manage_compliance']);
});

test('allowed U.S. destination continues beyond restriction validation', async ({ page }) => {
  await openCheckout(page, [restrictedProductId]);
  await fillCheckout(page, 'IL', '60601');
  await submitCheckout(page);
  await page.waitForURL(/order-received/);
});

test('fictional state restriction blocks without exposing internal details @a11y', async ({ page }) => {
  await openCheckout(page, [allowedProductId]);
  await fillCheckout(page, 'ZZ', '60601');
  await submitCheckout(page);
  await expectRestriction(page);
  const results = await new AxeBuilder({ page }).include('.woocommerce').analyze();
  expect(results.violations).toEqual([]);
});

test('exact postal restriction clears after an allowed postal code is revalidated', async ({ page }) => {
  await openCheckout(page, [allowedProductId]);
  await fillCheckout(page, 'MO', '00000');
  await page.locator('#billing_postcode').blur();
  await expectRestriction(page);
  await page.locator('#billing_postcode').fill('63101');
  await Promise.all([
    page.waitForResponse(
      (response) => response.url().includes('update_order_review') && response.ok(),
    ),
    page.evaluate(() => {
      const jquery = (window as unknown as {
        jQuery: (target: HTMLElement) => { trigger: (event: string) => void };
      }).jQuery;
      jquery(document.body).trigger('update_checkout');
    }),
  ]);
  await submitCheckout(page);
  await page.waitForURL(/order-received/);
  await expect(page.getByText(genericMessage)).toHaveCount(0);
});

test('destination product restriction clears after the restricted item is removed', async ({ page }) => {
  await openCheckout(page, [restrictedProductId, allowedProductId]);
  await fillCheckout(page, 'MO', '63101');
  await submitCheckout(page);
  await expectRestriction(page);
  await page.goto('/cart/');
  const row = page.locator('.wc-block-cart-items__row').filter({ hasText: 'Fictional Robusto' });
  await row.getByRole('button', { name: /remove/i }).click();
  await expect(row).toHaveCount(0);
  await expect(page.getByText('Estimated total').locator('..')).toContainText('$10.00');
  await page.goto('/checkout/');
  await fillCheckout(page, 'MO', '63101');
  await submitCheckout(page);
  await page.waitForURL(/order-received/);
});

test('forged allowed value cannot reach payment or create an order', async ({ page }) => {
  const before = Number(wpCli(['eval', 'echo count(wc_get_orders(array("limit"=>-1,"return"=>"ids")));']));
  await openCheckout(page, [restrictedProductId]);
  await fillCheckout(page, 'MO', '63101');
  await page.locator('form.checkout').evaluate((form) => {
    const forged = document.createElement('input');
    forged.type = 'hidden';
    forged.name = 'compadres_restriction_allowed';
    forged.value = '1';
    form.append(forged);
  });
  await submitCheckout(page);
  await expectRestriction(page);
  const after = Number(wpCli(['eval', 'echo count(wc_get_orders(array("limit"=>-1,"return"=>"ids")));']));
  expect(after).toBe(before);
  await expect(page).not.toHaveURL(/order-received/);
});

test('restriction administration enforces capability, nonce, revision, and output controls', async ({ page }) => {
  await login(page, 'restriction-store-admin');
  await page.goto('/wp-admin/admin.php?page=compadres-restrictions');
  await expect(page.getByRole('heading', { level: 1, name: 'Geographic Checkout Restrictions' })).toBeVisible();
  await page
    .getByLabel('Name', { exact: true })
    .fill('<script>window.compadresRestrictionXss=true</script>Fictional admin-managed rule');
  await page.getByLabel('State codes').fill('ZX');
  await page
    .getByLabel('Customer-facing blocked message')
    .fill(`<img src=x onerror="window.compadresRestrictionXss=true">${genericMessage}`);
  await page.getByLabel('Internal note').fill('<script>window.compadresRestrictionXss=true</script>Internal note');
  await page.getByLabel('Source name').fill('<b>E2E Fictional</b>');
  const createForm = page.locator('form').filter({
    has: page.locator('input[name="action"][value="compadres_save_restriction"]'),
  });
  await submitForm(page, createForm);
  await expect(page.getByText('Restriction rule saved.')).toBeVisible();
  let managedRow = page.locator('tbody tr').filter({ hasText: 'Fictional admin-managed rule' });
  const editUrl = await managedRow.getByRole('link', { name: 'Edit' }).getAttribute('href');
  expect(editUrl).not.toBeNull();
  const ruleId = editUrl?.match(/rule_id=(\d+)/)?.[1] ?? '';
  expect(ruleId).not.toBe('');
  expect(await page.evaluate(() => Boolean((window as unknown as { compadresRestrictionXss?: boolean }).compadresRestrictionXss))).toBeFalsy();
  await page.goto(editUrl as string);
  await expect(page.getByLabel('Name', { exact: true })).not.toHaveValue(/<script/i);
  await expect(page.getByLabel('Customer-facing blocked message')).not.toHaveValue(/<img/i);
  await expect(page.getByLabel('Internal note')).not.toHaveValue(/<script/i);
  await expect(page.getByLabel('Source name')).toHaveValue('E2E Fictional');
  await page.getByLabel('Name', { exact: true }).fill('Fictional admin-managed rule updated');
  const editForm = page.locator('form').filter({
    has: page.locator('input[name="action"][value="compadres_save_restriction"]'),
  });
  await submitForm(page, editForm);
  await expect(page.getByText('Restriction rule saved.')).toBeVisible();
  expect(
    wpCli([
      'db',
      'query',
      `SELECT CONCAT(name,'|',revision) FROM wp_compadres_restriction_rules WHERE id=${ruleId}`,
      '--skip-column-names',
    ]),
  ).toBe('Fictional admin-managed rule updated|2');
  expect(
    Number(
      wpCli([
        'db',
        'query',
        `SELECT COUNT(*) FROM wp_compadres_audit_log WHERE event_type='restriction.rule_updated' AND entity_id='${ruleId}'`,
        '--skip-column-names',
      ]),
    ),
  ).toBeGreaterThan(0);

  await page.goto(editUrl as string);
  const staleForm = page.locator('form').filter({
    has: page.locator('input[name="action"][value="compadres_save_restriction"]'),
  });
  const staleFields = await staleForm.evaluate((element: HTMLFormElement) => {
    const fields: Record<string, string> = {};
    new FormData(element).forEach((value, key) => {
      fields[key] = String(value);
    });
    return fields;
  });
  staleFields.revision = '1';
  staleFields.name = 'Stale overwrite attempt';
  const staleResponse = await page.request.post('/wp-admin/admin-post.php', {
    form: staleFields as Record<string, string>,
    maxRedirects: 0,
  });
  expect(staleResponse.status()).toBe(302);
  expect(
    wpCli([
      'db',
      'query',
      `SELECT CONCAT(name,'|',revision) FROM wp_compadres_restriction_rules WHERE id=${ruleId}`,
      '--skip-column-names',
    ]),
  ).toBe('Fictional admin-managed rule updated|2');

  await login(page, 'restriction-compliance');
  await page.goto('/wp-admin/admin.php?page=compadres-restrictions');
  await expect(page.getByRole('heading', { level: 1, name: 'Geographic Checkout Restrictions' })).toBeVisible();
  managedRow = page.locator('tbody tr').filter({ hasText: 'Fictional admin-managed rule updated' });
  await submitForm(
    page,
    managedRow.locator('form').filter({ has: page.locator('input[name="mode"][value="deactivate"]') }),
  );
  await expect(page.getByText('Restriction rule deactivated.')).toBeVisible();
  managedRow = page.locator('tbody tr').filter({ hasText: 'Fictional admin-managed rule updated' });
  await submitForm(
    page,
    managedRow.locator('form').filter({ has: page.locator('input[name="mode"][value="activate"]') }),
  );
  await expect(page.getByText('Restriction rule activated.')).toBeVisible();
  managedRow = page.locator('tbody tr').filter({ hasText: 'Fictional admin-managed rule updated' });
  await submitForm(
    page,
    managedRow.locator('form').filter({
      has: page.locator('input[name="action"][value="compadres_archive_restriction"]'),
    }),
  );
  await expect(page.getByText(/archived to preserve historical references/i)).toBeVisible();

  await login(page, 'restriction-unauthorized');
  const deniedPage = await page.request.get('/wp-admin/admin.php?page=compadres-restrictions');
  expect(deniedPage.status()).toBe(403);
  const deniedPost = await page.request.post('/wp-admin/admin-post.php', {
    form: { action: 'compadres_save_restriction', rule_id: '0', name: 'Unauthorized' },
  });
  expect(deniedPost.status()).toBe(403);

  await login(page, 'restriction-store-admin');
  const missingNonce = await page.request.post('/wp-admin/admin-post.php', {
    form: { action: 'compadres_save_restriction', rule_id: '0', name: 'Missing nonce' },
  });
  expect(missingNonce.status()).toBe(403);
});

test('fictional fixture lifecycle is idempotent, ownership-scoped, and production-disabled', async ({}, testInfo) => {
  test.skip(testInfo.project.name.includes('mobile'), 'Fixture lifecycle is environment-level coverage.');
  wpCli(['compadres', 'restriction-fixtures', 'remove']);
  wpCli(['compadres', 'restriction-fixtures', 'load']);
  wpCli(['compadres', 'restriction-fixtures', 'load']);
  expect(
    wpCli([
      'db',
      'query',
      "SELECT COUNT(*) FROM wp_compadres_restriction_rules WHERE fixture_key='development-product-deny-v1'",
      '--skip-column-names',
    ]),
  ).toBe('1');
  expect(() =>
    wpCli(['compadres', 'restriction-fixtures', 'load'], { APP_ENV: 'production' }),
  ).toThrow();
  wpCli(['compadres', 'restriction-fixtures', 'remove']);
  expect(
    wpCli([
      'db',
      'query',
      "SELECT COUNT(*) FROM wp_compadres_restriction_rules WHERE source_name='E2E Fictional'",
      '--skip-column-names',
    ]),
  ).not.toBe('0');
});
