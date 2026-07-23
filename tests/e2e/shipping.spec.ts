import { execFileSync } from 'node:child_process';
import { rmSync, writeFileSync } from 'node:fs';
import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

const password = 'CompadresTestOnly42';
const blockedMessage =
  'The selected shipping service cannot deliver cigars, which require an adult signature on delivery. Please choose an eligible shipping method or contact the store.';
const scenarioOption = 'compadres_shipping_mock_scenario';
const paymentCounterOption = 'compadres_shipping_test_payment_calls';
const paymentProbePath = 'wp-content/plugins/compadres-commerce/shipping-test-probe.php';
let originalAgeVerification: string | null = null;
let originalAgeGate: string | null = null;
let originalCod: string | null = null;
let originalScenario: string | null = null;
let originalCheckoutContent = '';
let originalProductState = '';
let productId = '';
let zoneId = '';
let restrictionRuleId = '';

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
      // Already absent.
    }
    return;
  }
  wpCli(['option', 'update', name, value, '--format=json']);
}

function setScenario(scenario: 'eligible' | 'ineligible' | 'unavailable' | 'none'): void {
  wpCli(['option', 'update', scenarioOption, JSON.stringify({ scenario }), '--format=json']);
  wpCli(['eval', 'WC_Cache_Helper::get_transient_version("shipping",true);']);
}

function configureAge(status: 'passed' | 'failed'): void {
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
      mock_status: status,
      mock_refresh_status: status,
    }),
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

async function openCheckout(page: Page): Promise<void> {
  await page.goto(`/?add-to-cart=${productId}`);
  await page.goto('/checkout/');
  await expect(page.getByRole('heading', { level: 1, name: 'Checkout' })).toBeVisible();
}

async function fillCheckout(page: Page, state = 'MO', postalCode = '63101'): Promise<void> {
  await page.locator('#billing_first_name').fill('Ada');
  await page.locator('#billing_last_name').fill('Lovelace');
  await page.locator('#billing_address_1').fill('1 Fictional Street');
  await page.locator('#billing_city').fill('Example City');
  await page.locator('#billing_postcode').fill(postalCode);
  await page.locator('#billing_phone').fill('3145550100');
  await page.locator('#billing_email').fill('shipping@example.test');
  if (await page.locator('#billing_country').isVisible()) {
    await page.locator('#billing_country').selectOption('US');
  }
  await selectState(page, state);
  await page.locator('#billing_postcode').blur();
}

async function submitCheckout(page: Page): Promise<void> {
  await page.getByRole('button', { name: /place order/i }).click();
}

async function expectShippingBlock(page: Page): Promise<void> {
  const error = page.locator('.woocommerce-error, .wc-block-components-notice-banner.is-error');
  await expect(error).toContainText(blockedMessage);
  await expect(page.locator('body')).not.toContainText(/compadres_mock|stack trace|exception|authorization/i);
  await expect(page).not.toHaveURL(/order-received/);
}

function orderCount(): number {
  return Number(wpCli(['eval', 'echo count(wc_get_orders(array("limit"=>-1,"return"=>"ids")));']));
}

function paymentCalls(): number {
  return Number(wpCli(['eval', `echo (int) get_option('${paymentCounterOption}', 0);`]));
}

function resetPaymentCalls(): void {
  wpCli(['option', 'update', paymentCounterOption, '0']);
}

function createRestrictionRule(): string {
  const values = {
    name: 'Fictional shipping-order restriction',
    enabled: '1',
    priority: '100',
    country: 'US',
    state: 'ZZ',
    city: '',
    postal_code: '',
    postal_prefix: '',
    product_id: '',
    category_id: '',
    brand_id: '',
    effective_at: '2020-01-01T00:00',
    expires_at: '',
    blocked_message: 'We cannot ship the current cart to this destination. Please update the shipping address or remove the restricted item.',
    notes: 'E2E fictional ordering rule',
    source_name: 'E2E Fictional',
    source_url: 'https://example.test/review',
    review_date: '2026-07-22',
  };
  const encoded = Buffer.from(JSON.stringify(values)).toString('base64');
  return wpCli([
    'eval',
    `$v=json_decode(base64_decode('${encoded}'),true);global $wpdb;` +
      '$i=\\Compadres\\Commerce\\Restrictions\\RestrictionRuleInput::fromArray($v,wp_timezone());' +
      '$r=new \\Compadres\\Commerce\\Restrictions\\WordPressRestrictionAdminRepository($wpdb);echo $r->create($i);',
  ]);
}

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
  originalAgeVerification = getOption('compadres_age_verification');
  originalAgeGate = getOption('compadres_age_gate');
  originalCod = getOption('woocommerce_cod_settings');
  originalScenario = getOption(scenarioOption);
  originalCheckoutContent = wpCli([
    'eval',
    '$p=get_post((int)get_option("woocommerce_checkout_page_id"));echo $p ? $p->post_content : "";',
  ]);
  wpCli([
    'eval',
    '$id=(int)get_option("woocommerce_checkout_page_id");wp_update_post(array("ID"=>$id,"post_content"=>"[woocommerce_checkout]"));',
  ]);
  wpCli(['option', 'update', 'compadres_age_gate', JSON.stringify({ enabled: false }), '--format=json']);
  configureAge('passed');
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
  productId = wpCli(['eval', 'echo wc_get_product_id_by_sku("DEV-FICTIONAL-TORO-SINGLE");']);
  originalProductState = wpCli([
    'eval',
    `$p=wc_get_product(${productId});echo wp_json_encode(array('virtual'=>$p->is_virtual(),'manage_stock'=>$p->get_manage_stock(),'stock_quantity'=>$p->get_stock_quantity(),'stock_status'=>$p->get_stock_status()));`,
  ]);
  wpCli([
    'eval',
    `$p=wc_get_product(${productId});$p->set_virtual(false);$p->set_manage_stock(false);$p->set_stock_status('instock');$p->save();`,
  ]);
  zoneId = wpCli([
    'eval',
    '$zones=WC_Shipping_Zones::get_zones();foreach($zones as $z){if("Compadres E2E Shipping"===$z["zone_name"]){(new WC_Shipping_Zone((int)$z["zone_id"]))->delete();}}' +
      '$zone=new WC_Shipping_Zone();$zone->set_zone_name("Compadres E2E Shipping");$zone->set_zone_order(0);$zone->add_location("US","country");$zone->save();$zone->add_shipping_method("compadres_mock_shipping");echo $zone->get_id();',
  ]);
  const probe = `<?php
/** Plugin Name: Compadres Shipping E2E Payment Probe */
add_filter('woocommerce_payment_successful_result', static function ($result, $order_id) {
    update_option('${paymentCounterOption}', (int) get_option('${paymentCounterOption}', 0) + 1, false);
    return $result;
}, 10, 2);
`;
  writeFileSync(paymentProbePath, probe);
  wpCli(['plugin', 'activate', 'compadres-commerce/shipping-test-probe.php']);
  resetPaymentCalls();
  ensureUser('shipping-store-admin', 'administrator');
  restrictionRuleId = createRestrictionRule();
  setScenario('eligible');
});

test.afterAll(() => {
  if (restrictionRuleId !== '') {
    wpCli([
      'eval',
      `global $wpdb;$id=${Number(restrictionRuleId)};$wpdb->delete($wpdb->prefix.'compadres_restriction_targets',array('rule_id'=>$id),array('%d'));$wpdb->delete($wpdb->prefix.'compadres_restriction_rules',array('id'=>$id),array('%d'));`,
    ]);
  }
  if (zoneId !== '') wpCli(['eval', `(new WC_Shipping_Zone(${Number(zoneId)}))->delete();`]);
  try {
    wpCli(['plugin', 'deactivate', 'compadres-commerce/shipping-test-probe.php']);
  } catch {
    // Already inactive.
  }
  rmSync(paymentProbePath, { force: true });
  restoreOption('compadres_age_verification', originalAgeVerification);
  restoreOption('compadres_age_gate', originalAgeGate);
  restoreOption('woocommerce_cod_settings', originalCod);
  restoreOption(scenarioOption, originalScenario);
  try {
    wpCli(['option', 'delete', paymentCounterOption]);
  } catch {
    // Already absent.
  }
  const encodedCheckout = Buffer.from(originalCheckoutContent).toString('base64');
  wpCli([
    'eval',
    `$id=(int)get_option('woocommerce_checkout_page_id');wp_update_post(array('ID'=>$id,'post_content'=>base64_decode('${encodedCheckout}')));`,
  ]);
  const encodedProduct = Buffer.from(originalProductState).toString('base64');
  wpCli([
    'eval',
    `$s=json_decode(base64_decode('${encodedProduct}'),true);$p=wc_get_product(${productId});if($p&&is_array($s)){$p->set_virtual((bool)$s['virtual']);$p->set_manage_stock((bool)$s['manage_stock']);$p->set_stock_quantity($s['stock_quantity']);$p->set_stock_status((string)$s['stock_status']);$p->save();}`,
  ]);
});

test.beforeEach(({}, testInfo) => {
  testInfo.setTimeout(90_000);
  configureAge('passed');
  setScenario('eligible');
  resetPaymentCalls();
});

test('eligible test service permits checkout and stores only minimal shipping metadata @a11y', async ({ page }) => {
  test.setTimeout(60_000);
  await openCheckout(page);
  await fillCheckout(page);
  await expect(page.getByText(/cigar deliveries use adult signature required service/i)).toBeVisible();
  await expect(page.getByText('Adult Signature Eligible Test Service')).toBeVisible();
  const checkoutAxe = await new AxeBuilder({ page })
    .include('.woocommerce-info')
    .include('#order_review')
    .analyze();
  expect(checkoutAxe.violations).toEqual([]);
  await submitCheckout(page);
  await page.waitForURL(/order-received/);
  expect(paymentCalls()).toBe(1);

  const orderId = page.url().match(/order-received\/(\d+)/)?.[1] ?? '';
  expect(orderId).not.toBe('');
  const metadata = JSON.parse(
    wpCli([
      'eval',
      `$o=wc_get_order(${Number(orderId)});$out=array();foreach($o->get_meta_data() as $m){$d=$m->get_data();if(str_starts_with((string)$d['key'],'_compadres_shipping_')){$out[$d['key']]=$d['value'];}}echo wp_json_encode($out);`,
    ]),
  ) as Record<string, string>;
  expect(Object.keys(metadata).sort()).toEqual(
    [
      '_compadres_shipping_adult_signature_required',
      '_compadres_shipping_eligibility',
      '_compadres_shipping_eligibility_checked_at',
      '_compadres_shipping_provider',
      '_compadres_shipping_service',
      '_compadres_shipping_service_reference',
    ].sort(),
  );
  expect(metadata._compadres_shipping_adult_signature_required).toBe('yes');
  expect(metadata._compadres_shipping_provider).toBe('mock');
  expect(metadata._compadres_shipping_service).toBe('compadres_mock_eligible');
  expect(metadata._compadres_shipping_service_reference).toBe('mock-compadres_mock_eligible');
  expect(metadata._compadres_shipping_eligibility).toBe('allowed');
  expect(Number.isNaN(Date.parse(metadata._compadres_shipping_eligibility_checked_at))).toBeFalsy();

  await login(page, 'shipping-store-admin');
  await page.goto(`/wp-admin/admin.php?page=wc-orders&action=edit&id=${orderId}`);
  const details = page.locator('.compadres-shipping-meta');
  await expect(details).toContainText('Adult Signature Required: Yes');
  await expect(details).toContainText('Shipping provider: mock');
  await expect(details).toContainText('Shipping service: compadres_mock_eligible');
  await expect(details).toContainText('Eligibility result: allowed');
  await expect(page.locator('link[href*="shipping-admin.css"]')).toHaveCount(1);
  await expect(details.locator('p')).toHaveCSS('color', 'rgb(80, 87, 94)');
  const adminAxe = await new AxeBuilder({ page }).include('.compadres-shipping-meta').analyze();
  expect(adminAxe.violations).toEqual([]);
});

test('service without adult signature is blocked before payment and ignores forged eligibility @a11y', async ({ page }) => {
  setScenario('ineligible');
  const before = orderCount();
  await openCheckout(page);
  await fillCheckout(page);
  await expect(page.getByText('Test Service (no adult signature)')).toBeVisible();
  await page.locator('form.checkout').evaluate((form) => {
    const forged = document.createElement('input');
    forged.type = 'hidden';
    forged.name = 'compadres_shipping_eligible';
    forged.value = '1';
    form.append(forged);
  });
  await submitCheckout(page);
  await expectShippingBlock(page);
  expect(paymentCalls()).toBe(0);
  expect(orderCount()).toBe(before);
  const axe = await new AxeBuilder({ page }).include('.woocommerce-error').include('#shipping_method').analyze();
  expect(axe.violations).toEqual([]);
});

test('provider unavailable fails closed before payment while preserving the cart', async ({ page }) => {
  test.setTimeout(60_000);
  setScenario('unavailable');
  await openCheckout(page);
  await fillCheckout(page);
  await submitCheckout(page);
  await expectShippingBlock(page);
  expect(paymentCalls()).toBe(0);
  await page.goto('/cart/');
  await expect(
    page.locator('.cart_item, .wc-block-cart-items__row').filter({ hasText: 'Fictional Toro' }),
  ).toHaveCount(1);
});

test('no eligible service blocks checkout and creates no order', async ({ page }) => {
  setScenario('none');
  const before = orderCount();
  await openCheckout(page);
  await fillCheckout(page);
  await submitCheckout(page);
  await expectShippingBlock(page);
  expect(paymentCalls()).toBe(0);
  expect(orderCount()).toBe(before);
});

test('geographic restriction still blocks before otherwise eligible shipping', async ({ page }) => {
  setScenario('eligible');
  const before = orderCount();
  await openCheckout(page);
  await fillCheckout(page, 'ZZ');
  await submitCheckout(page);
  await expect(page.locator('.woocommerce-error')).toContainText(/cannot ship the current cart to this destination/i);
  expect(paymentCalls()).toBe(0);
  expect(orderCount()).toBe(before);
});

test('shipping eligibility does not satisfy authoritative age verification', async ({ page }) => {
  configureAge('failed');
  const before = orderCount();
  await openCheckout(page);
  await fillCheckout(page);
  await submitCheckout(page);
  await expect(page.locator('.woocommerce-error')).toContainText(/could not verify/i);
  expect(paymentCalls()).toBe(0);
  expect(orderCount()).toBe(before);
});

test('pay-for-order hook validates immutable shipping snapshots and fails closed', async () => {
  const completeSnapshot = {
    _compadres_shipping_adult_signature_required: 'yes',
    _compadres_shipping_provider: 'mock',
    _compadres_shipping_service: 'compadres_mock_eligible',
    _compadres_shipping_eligibility: 'allowed',
    _compadres_shipping_eligibility_checked_at: new Date().toISOString().replace(/\.\d{3}Z$/, '+00:00'),
  };
  const outcome = (snapshot: Record<string, string>): string => {
    const encoded = Buffer.from(JSON.stringify(snapshot)).toString('base64');
    return wpCli([
      'eval',
      `$m=json_decode(base64_decode('${encoded}'),true);$o=wc_create_order();$o->add_product(wc_get_product(${productId}),1);` +
        '$o->set_shipping_country("US");$o->set_shipping_state("MO");$o->set_shipping_postcode("63101");$o->set_status("pending");' +
        'foreach($m as $k=>$v){$o->update_meta_data($k,$v);}$o->save();' +
        'try{do_action("woocommerce_before_pay_action",$o);$r="allowed";}catch(Throwable $e){$r="blocked";}' +
        '$o->delete(true);echo $r;',
    ]);
  };

  expect(outcome({})).toBe('blocked');
  expect(
    outcome({ ...completeSnapshot, _compadres_shipping_eligibility_checked_at: 'not-a-timestamp' }),
  ).toBe('blocked');
  expect(outcome({ ...completeSnapshot, _compadres_shipping_provider: 'forged-provider' })).toBe('blocked');
  expect(outcome(completeSnapshot)).toBe('allowed');

  setScenario('ineligible');
  expect(outcome(completeSnapshot)).toBe('blocked');
  setScenario('eligible');

  expect(
    wpCli([
      'eval',
      'global $wpdb;echo (string)$wpdb->get_var("SELECT failure_reason FROM {$wpdb->prefix}compadres_audit_log WHERE event_type=\'shipping.checkout_blocked\' ORDER BY audit_id DESC LIMIT 1");',
    ]),
  ).toMatch(/service_unsupported|invalid_order_shipping_snapshot/);
  expect(paymentCalls()).toBe(0);
});

test('mock method is production-prohibited, staging-gated, and clearly labeled', async () => {
  const expression =
    '$m=new \\Compadres\\Commerce\\Shipping\\MockShippingMethod(1);echo wp_json_encode(array("available"=>$m->is_available(array()),"title"=>$m->method_title));';
  const production = JSON.parse(wpCli(['eval', expression], { APP_ENV: 'production' })) as {
    available: boolean;
    title: string;
  };
  const forgedProduction = JSON.parse(
    wpCli(['eval', `$_POST['COMPADRES_ENABLE_MOCK_SHIPPING']='1';${expression}`], {
      APP_ENV: 'production',
      COMPADRES_ENABLE_MOCK_SHIPPING: '1',
    }),
  ) as { available: boolean; title: string };
  const stagingDisabled = JSON.parse(wpCli(['eval', expression], { APP_ENV: 'staging' })) as {
    available: boolean;
  };
  const stagingEnabled = JSON.parse(
    wpCli(['eval', expression], {
      APP_ENV: 'staging',
      COMPADRES_ENABLE_MOCK_SHIPPING: '1',
    }),
  ) as { available: boolean };

  expect(production.available).toBeFalsy();
  expect(forgedProduction.available).toBeFalsy();
  expect(stagingDisabled.available).toBeFalsy();
  expect(stagingEnabled.available).toBeTruthy();
  expect(production.title).toMatch(/test shipping.*development only/i);
});

test('shipping audit events stay limited and exclude sensitive checkout data', async ({ page }) => {
  setScenario('ineligible');
  await openCheckout(page);
  await fillCheckout(page);
  await submitCheckout(page);
  await expectShippingBlock(page);
  const rows = JSON.parse(
    wpCli([
      'eval',
      'global $wpdb;$rows=$wpdb->get_results("SELECT event_type,request_context FROM {$wpdb->prefix}compadres_audit_log WHERE event_type LIKE \'shipping.%\' ORDER BY audit_id DESC LIMIT 20",ARRAY_A);echo wp_json_encode($rows);',
    ]),
  ) as Array<{ event_type: string; request_context: string }>;
  expect(rows.length).toBeGreaterThan(0);
  expect(new Set(rows.map(({ event_type }) => event_type))).toEqual(
    new Set(['shipping.eligibility_checked', 'shipping.checkout_blocked', 'shipping.settings_updated']),
  );
  const serialized = JSON.stringify(rows).toLowerCase();
  expect(serialized).not.toMatch(/fictional street|shipping@example|cookie|authorization|credential|carrier_payload/);
});
