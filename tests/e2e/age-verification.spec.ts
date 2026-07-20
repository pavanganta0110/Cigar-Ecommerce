import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

const optionName = 'compadres_age_verification';
const password = 'CompadresTestOnly42';
let originalVerification: string | null = null;
let originalCod: string | null = null;
let originalAgeGate: string | null = null;
let originalCheckoutContent = '';
let originalProductState = '';
let productId = '';

function wpCli(args: string[]): string {
  return execFileSync('docker', ['compose', 'run', '--rm', 'wpcli', ...args], {
    cwd: process.cwd(),
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
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

function configureVerification(
  status: string,
  overrides: Record<string, boolean | string> = {},
): void {
  const settings = {
    enabled: true,
    provider: 'mock',
    requires_date_of_birth: false,
    hosted_url_template: '',
    production_approved: false,
    mock_status: status,
    mock_refresh_status: status,
    ...overrides,
  };
  wpCli(['option', 'update', optionName, JSON.stringify(settings), '--format=json']);
}

function configureEntryGate(enabled: boolean): void {
  const settings =
    originalAgeGate === null ? {} : (JSON.parse(originalAgeGate) as Record<string, unknown>);
  wpCli([
    'option',
    'update',
    'compadres_age_gate',
    JSON.stringify({ ...settings, enabled }),
    '--format=json',
  ]);
}

function ensureUser(username: string, role: string): void {
  try {
    wpCli(['user', 'get', username, '--field=ID']);
  } catch {
    wpCli(['user', 'create', username, `${username}@example.test`, '--role=subscriber', '--porcelain']);
  }
  wpCli(['user', 'update', username, `--role=${role}`, `--user_pass=${password}`]);
}

async function acceptAgeGate(page: Page): Promise<void> {
  await page.goto('/');
  const dialog = page.locator('[data-age-gate] [role="dialog"]');
  if (await dialog.isVisible()) {
    await page.locator('[data-age-confirm]').click();
    await expect(dialog).toBeHidden();
  }
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
  await page.goto('/wp-admin/');
}

async function openCheckout(page: Page): Promise<void> {
  await acceptAgeGate(page);
  await page.goto(`/?add-to-cart=${productId}`);
  await page.goto('/checkout/');
  await expect(page.getByRole('heading', { level: 1, name: 'Checkout' })).toBeVisible();
}

async function fillCheckout(page: Page): Promise<void> {
  await page.locator('#billing_first_name').fill('Ada');
  await page.locator('#billing_last_name').fill('Lovelace');
  await page.locator('#billing_address_1').fill('1 Main Street');
  await page.locator('#billing_city').fill('St. Louis');
  await page.locator('#billing_postcode').fill('63101');
  await page.locator('#billing_phone').fill('3145550100');
  await page.locator('#billing_email').fill('ada@example.test');
  if (await page.locator('#billing_country').isVisible()) {
    await page.locator('#billing_country').selectOption('US');
  }
  if (await page.locator('#billing_state').isVisible()) {
    await page.locator('#billing_state').selectOption('MO');
  }
}

async function submitCheckout(page: Page): Promise<void> {
  await page.getByRole('button', { name: /place order/i }).click();
}

function createManualReviewOrder(): { id: string; url: string } {
  const output = wpCli([
    'eval',
    `$p=wc_get_product(${productId});$o=wc_create_order();$o->add_product($p,1);` +
      '$o->set_billing_first_name("Manual");$o->set_billing_last_name("Review");' +
      '$o->set_billing_email("manual@example.test");' +
      '$o->update_meta_data("_compadres_age_provider","agechecker");' +
      '$o->update_meta_data("_compadres_age_reference","manual-browser");' +
      '$o->update_meta_data("_compadres_age_status","manual_review");' +
      '$o->update_meta_data("_compadres_age_verified_at",gmdate(DATE_ATOM));' +
      '$o->update_meta_data("_compadres_age_expires_at","");' +
      '$o->update_meta_data("_compadres_age_reason_code","provider_review");' +
      '$o->update_meta_data("_compadres_age_manual_action","");' +
      '$o->update_meta_data("_compadres_age_reviewer_id",0);' +
      '$o->update_meta_data("_compadres_age_manual_decided_at","");' +
      '$o->calculate_totals();$o->save();echo $o->get_id()."|".$o->get_edit_order_url();',
  ]);
  const [id, url] = output.split('|');
  return { id, url };
}

async function expectBlocked(page: Page, message: RegExp): Promise<void> {
  await submitCheckout(page);
  const error = page.locator('.woocommerce-error, .wc-block-components-notice-banner.is-error');
  await expect(error).toContainText(message);
  await expect(page.getByText(/Ember Quay Fictional Robusto Single/i).first()).toBeVisible();
  await expect(page).toHaveURL(/checkout/);
}

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
  originalVerification = getOption(optionName);
  originalCod = getOption('woocommerce_cod_settings');
  originalAgeGate = getOption('compadres_age_gate');
  originalCheckoutContent = wpCli([
    'eval',
    '$p=get_post((int)get_option("woocommerce_checkout_page_id"));echo $p ? $p->post_content : "";',
  ]);
  wpCli([
    'eval',
    '$id=(int)get_option("woocommerce_checkout_page_id");wp_update_post(array("ID"=>$id,"post_content"=>"[woocommerce_checkout]"));',
  ]);
  productId = wpCli(['eval', 'echo wc_get_product_id_by_sku("DEV-FICTIONAL-ROBUSTO-SINGLE");']);
  originalProductState = wpCli([
    'eval',
    `$p=wc_get_product(${productId});echo wp_json_encode(array('virtual'=>$p->is_virtual(),'manage_stock'=>$p->get_manage_stock(),'stock_quantity'=>$p->get_stock_quantity(),'stock_status'=>$p->get_stock_status()));`,
  ]);
  wpCli([
    'eval',
    `$p=wc_get_product(${productId});$p->set_virtual(true);$p->set_manage_stock(false);$p->set_stock_status('instock');$p->save();`,
  ]);
  wpCli([
    'option',
    'update',
    'woocommerce_cod_settings',
    JSON.stringify({ enabled: 'yes', title: 'Cash on delivery', description: '', instructions: '', enable_for_methods: [], enable_for_virtual: 'yes' }),
    '--format=json',
  ]);
  wpCli(['eval', '\\Compadres\\Commerce\\Security\\RoleManager::install();']);
  ensureUser('age-review-admin', 'compadres_store_administrator');
  ensureUser('age-review-unauthorized', 'subscriber');
});

test.afterAll(() => {
  restoreOption(optionName, originalVerification);
  restoreOption('woocommerce_cod_settings', originalCod);
  restoreOption('compadres_age_gate', originalAgeGate);
  wpCli([
    'eval',
    `$id=(int)get_option('woocommerce_checkout_page_id');wp_update_post(array('ID'=>$id,'post_content'=>base64_decode('${Buffer.from(originalCheckoutContent).toString('base64')}')));$s=json_decode(base64_decode('${Buffer.from(originalProductState).toString('base64')}'),true);$p=wc_get_product(${productId});if($p&&is_array($s)){$p->set_virtual((bool)$s['virtual']);$p->set_manage_stock((bool)$s['manage_stock']);$p->set_stock_quantity($s['stock_quantity']);$p->set_stock_status((string)$s['stock_status']);$p->save();}`,
  ]);
});

test.beforeEach(() => {
  configureVerification('passed');
  configureEntryGate(false);
});

test('authoritative pass reaches payment and stores only normalized order metadata', async ({ page }) => {
  await openCheckout(page);
  await fillCheckout(page);
  await expect(page.getByLabel('Date of birth')).toHaveCount(0);
  await submitCheckout(page);
  await page.waitForURL(/order-received/);
  const orderId = page.url().match(/order-received\/(\d+)/)?.[1] ?? '';
  expect(wpCli(['eval', `$o=wc_get_order(${orderId});echo $o->get_payment_method();`])).toBe('cod');
  const metadata = JSON.parse(
    wpCli([
      'eval',
      `$o=wc_get_order(${orderId});echo wp_json_encode(array_values(array_filter($o->get_meta_data(),fn($m)=>str_starts_with($m->key,'_compadres_age'))));`,
    ]),
  ) as Array<{ key: string; value: unknown }>;
  expect(metadata.map(({ key }) => key).sort()).toEqual(
    [
      '_compadres_age_expires_at',
      '_compadres_age_manual_action',
      '_compadres_age_manual_decided_at',
      '_compadres_age_provider',
      '_compadres_age_reason_code',
      '_compadres_age_reference',
      '_compadres_age_reviewer_id',
      '_compadres_age_status',
      '_compadres_age_verified_at',
    ].sort(),
  );
  expect(metadata.some(({ key }) => key.includes('date_of_birth'))).toBeFalsy();
});

test('entry-gate cookie and forged browser status cannot bypass unconfigured verification', async ({ page }) => {
  configureEntryGate(true);
  wpCli(['option', 'update', optionName, JSON.stringify({ enabled: true, provider: '' }), '--format=json']);
  await acceptAgeGate(page);
  await expect
    .poll(async () => (await page.context().cookies()).some(({ name }) => name === 'compadres_age_confirmed'))
    .toBeTruthy();
  configureEntryGate(false);
  await page.goto(`/?add-to-cart=${productId}`);
  await page.goto('/checkout/');
  await fillCheckout(page);
  await page.locator('form.checkout').evaluate((form) => {
    const forged = document.createElement('input');
    forged.type = 'hidden';
    forged.name = 'compadres_age_verification_status';
    forged.value = 'passed';
    form.append(forged);
  });
  await expectBlocked(page, /verification.*unavailable|cannot continue/i);
});

test('failed, expired, and provider-unavailable results fail closed without internals', async ({ page }) => {
  test.setTimeout(60_000);
  for (const scenario of [
    { status: 'failed', message: /could not verify/i },
    { status: 'expired', message: /expired.*verify again/i },
  ]) {
    configureVerification(scenario.status);
    await openCheckout(page);
    await fillCheckout(page);
    await expectBlocked(page, scenario.message);
  }

  configureVerification('passed', {
    provider: 'agechecker',
    hosted_url_template: 'https://verify.example.test/{reference}?return_url={return_url}',
  });
  await openCheckout(page);
  await fillCheckout(page);
  await expectBlocked(page, /temporarily unavailable|cannot continue/i);
  await expect(page.locator('body')).not.toContainText(/authorization|exception|stack trace/i);
});

test('pending hosted workflow is safe and refreshes once to an authoritative pass', async ({ page }) => {
  configureVerification('pending', {
    mock_refresh_status: 'passed',
    hosted_url_template: 'https://verify.example.test/{reference}?return_url={return_url}',
  });
  await openCheckout(page);
  await fillCheckout(page);
  await expectBlocked(page, /still pending/i);
  const continuation = page.getByRole('link', { name: /continue secure verification with agechecker/i });
  await expect(continuation).toHaveAttribute('href', /^https:\/\/verify\.example\.test\/mock-local\?return_url=/);
  await expect(continuation).toHaveAttribute('target', '_blank');
  await expect(continuation).toHaveAttribute('rel', /noopener/);
  const refresh = page.getByRole('link', { name: /refresh verification status/i });
  await expect(refresh).toBeVisible();
  await refresh.click();
  await expect(page).toHaveURL(/checkout/);
  await fillCheckout(page);
  await submitCheckout(page);
  await page.waitForURL(/order-received/);
});

test('DOB is conditional, validated server-side, and never persisted or audited', async ({ page }) => {
  configureVerification('passed', { requires_date_of_birth: true });
  await openCheckout(page);
  await fillCheckout(page);
  const dob = page.getByLabel('Date of birth');
  await expect(dob).toBeVisible();
  await expect(dob).toHaveAttribute('aria-required', 'true');
  await dob.evaluate((input: HTMLInputElement) => {
    input.setAttribute('type', 'text');
    input.value = '2025-02-30';
  });
  await expectBlocked(page, /valid past date of birth/i);
  await dob.fill('1990-01-02');
  await submitCheckout(page);
  await page.waitForURL(/order-received/);
  const orderId = page.url().match(/order-received\/(\d+)/)?.[1] ?? '';
  expect(
    wpCli([
      'eval',
      `$o=wc_get_order(${orderId});echo $o->get_meta('compadres_date_of_birth',true).$o->get_meta('_compadres_date_of_birth',true);`,
    ]),
  ).toBe('');
  const auditDump = wpCli([
    'eval',
    `global $wpdb;echo wp_json_encode($wpdb->get_results("SELECT previous_value,new_value,request_context FROM {$wpdb->prefix}compadres_audit_log WHERE event_type LIKE 'age_verification.%' ORDER BY audit_id DESC LIMIT 20",ARRAY_A));`,
  ]);
  expect(auditDump).not.toContain('1990-01-02');
  expect(auditDump).not.toContain('billing_address');
});

test('manual-review controls enforce capability, nonce, state, and one decision', async ({ page }, testInfo) => {
  test.setTimeout(60_000);
  test.skip(testInfo.project.name.includes('mobile'), 'Administrative security is covered on desktop.');
  const order = createManualReviewOrder();
  await login(page, 'age-review-admin');
  await page.goto(order.url);
  await expect(page.getByRole('heading', { name: /age-verification manual decision/i })).toBeVisible();
  const controls = page.locator('#compadres-age-manual-decision');
  const nonce = await controls.locator('input[name="compadres_age_nonce"]').inputValue();
  wpCli(['option', 'update', `compadres_age_decision_lock_${order.id}`, '1']);
  await controls.getByLabel(/optional non-sensitive reason/i).fill('<b>Reviewed</b>');
  const orderForm = controls.locator('xpath=ancestor::form');
  await Promise.all([
    page.waitForNavigation(),
    orderForm.evaluate((form: HTMLFormElement) => {
      const submitter = form.querySelector<HTMLButtonElement>(
        '#compadres-age-manual-decision button[name="decision"][value="approved"]',
      );
      if (!submitter) throw new Error('Missing age-verification approval control.');
      form.requestSubmit(submitter);
    }),
  ]);
  await expect(page.locator('.notice-success')).toContainText(/approved/i);
  expect(getOption(`compadres_age_decision_lock_${order.id}`)).toBeNull();
  const stored = JSON.parse(
    wpCli([
      'eval',
      `$o=wc_get_order(${order.id});echo wp_json_encode(array('status'=>$o->get_meta('_compadres_age_status',true),'action'=>$o->get_meta('_compadres_age_manual_action',true),'reviewer'=>$o->get_meta('_compadres_age_reviewer_id',true),'decided'=>$o->get_meta('_compadres_age_manual_decided_at',true),'expires'=>$o->get_meta('_compadres_age_expires_at',true)));`,
    ]),
  ) as Record<string, string>;
  expect(stored.status).toBe('passed');
  expect(stored.action).toBe('approved');
  expect(Number(stored.reviewer)).toBeGreaterThan(0);
  expect(stored.decided).toMatch(/^\d{4}-\d{2}-\d{2}T/);
  expect(Date.parse(stored.expires)).toBeGreaterThan(Date.parse(stored.decided));

  const duplicate = await page.request.post('/wp-admin/admin-post.php', {
    form: {
      action: 'compadres_age_manual_decision',
      order_id: order.id,
      decision: 'approved',
      reason: 'again',
      compadres_age_nonce: nonce,
    },
    maxRedirects: 0,
  });
  expect(duplicate.status()).toBe(409);

  await page.context().clearCookies();
  await login(page, 'age-review-unauthorized');
  const unauthorized = await page.request.post('/wp-admin/admin-post.php', {
    form: { action: 'compadres_age_manual_decision', order_id: order.id, decision: 'rejected' },
    maxRedirects: 0,
  });
  expect(unauthorized.status()).toBe(403);
});

test('checkout verification controls have no focused Axe violations @a11y', async ({ page }) => {
  test.setTimeout(60_000);
  configureVerification('pending', {
    requires_date_of_birth: true,
    mock_refresh_status: 'passed',
    hosted_url_template: 'https://verify.example.test/{reference}?return_url={return_url}',
  });
  await openCheckout(page);
  await fillCheckout(page);
  await page.getByLabel('Date of birth').fill('1990-01-02');
  await expectBlocked(page, /still pending/i);
  const results = await new AxeBuilder({ page })
    .include('form.checkout')
    .include('.woocommerce-error')
    .analyze();
  expect(results.violations).toEqual([]);
});
