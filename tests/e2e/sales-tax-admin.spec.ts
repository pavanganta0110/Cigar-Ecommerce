import { execFileSync } from 'node:child_process';
import { expect, test, type Page } from '@playwright/test';

const password = 'CompadresTestOnly42';
let orderIds: number[] = [];
let productId = 0;
let taxRateId = 0;

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
  wpCli(['eval', '\\Compadres\\Commerce\\Security\\RoleManager::install();']);
  ensureUser('report-finance', 'compadres_tax_finance_viewer');
  ensureUser('report-unauthorized', 'subscriber');

  const fixture = wpCli([
    'eval',
    `
$product = new WC_Product_Simple();
$product->set_name('Dashboard Test Cigar');
$product->set_sku('DASHBOARD-TEST');
$product->set_regular_price('50');
$product->set_manage_stock(true);
$product->set_stock_quantity(10);
$product->set_status('publish');
$product_id = $product->save();
$tax_rate_id = WC_Tax::_insert_tax_rate(array(
  'tax_rate_country' => 'US',
  'tax_rate_state' => '',
  'tax_rate' => '10.0000',
  'tax_rate_name' => 'Dashboard Test Tax',
  'tax_rate_priority' => 1,
  'tax_rate_compound' => 0,
  'tax_rate_shipping' => 0,
  'tax_rate_order' => 0,
  'tax_rate_class' => '',
));
$create = static function(string $state, string $status, float $subtotal, float $total, float $tax, int $quantity) use ($product_id, $tax_rate_id): array {
  $order = wc_create_order();
  $order->set_shipping_state($state);
  $order->set_billing_state($state);
  $item = new WC_Order_Item_Product();
  $item->set_product_id($product_id);
  $item->set_name('Dashboard Test Cigar');
  $item->set_quantity($quantity);
  $item->set_subtotal($subtotal);
  $item->set_total($total);
  $item->set_taxes(array('subtotal' => array($tax_rate_id => $tax), 'total' => array($tax_rate_id => $tax)));
  $order->add_item($item);
  $shipping = new WC_Order_Item_Shipping();
  $shipping->set_method_title('Dashboard test shipping');
  $shipping->set_method_id('dashboard_test');
  $shipping->set_total(8);
  $order->add_item($shipping);
  $order->calculate_totals(false);
  $order->set_cart_tax($tax);
  $order->set_status($status);
  $order->save();
  return array($order->get_id(), $item->get_id());
};
list($mo_order, $mo_item) = $create('MO', 'completed', 100, 90, 9, 2);
list($il_order) = $create('IL', 'processing', 50, 50, 5, 1);
wc_create_refund(array(
  'amount' => 22,
  'reason' => 'Dashboard test partial refund',
  'order_id' => $mo_order,
  'refund_payment' => false,
  'restock_items' => false,
  'line_items' => array($mo_item => array('qty' => 1, 'refund_total' => 20, 'refund_tax' => array($tax_rate_id => 2))),
));
echo wp_json_encode(array('product' => $product_id, 'tax_rate' => $tax_rate_id, 'orders' => array($mo_order, $il_order)));
`,
  ]);
  const parsed = JSON.parse(fixture) as { product: number; tax_rate: number; orders: number[] };
  productId = parsed.product;
  taxRateId = parsed.tax_rate;
  orderIds = parsed.orders;
});

test.afterAll(() => {
  for (const id of orderIds) {
    wpCli(['eval', `$order=wc_get_order(${id}); if ($order) { $order->delete(true); }`]);
  }
  if (productId) {
    wpCli(['eval', `$product=wc_get_product(${productId}); if ($product) { $product->delete(true); }`]);
  }
  if (taxRateId) {
    wpCli(['eval', `WC_Tax::_delete_tax_rate(${taxRateId});`]);
  }
});

test('staff portal uses Compadres branding without visible WordPress branding', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name.includes('mobile'), 'Administrative branding is covered on desktop.');
  await page.goto('/wp-login.php');
  await expect(page.locator('#login h1 a')).toHaveText('Compadres Cigars Admin Portal');
  await expect(page).not.toHaveTitle(/WordPress/i);

  await login(page, 'report-finance');
  await page.goto('/wp-admin/admin.php?page=compadres-sales-tax');

  await expect(page.locator('#wp-admin-bar-wp-logo')).toHaveCount(0);
  await expect(page.locator('#wpfooter')).not.toContainText(/WordPress|Version\s+\d/i);
  await expect(page.locator('body')).not.toContainText(/WordPress/i);
  await expect(page).not.toHaveTitle(/WordPress/i);
});

test('finance staff can review state tax, product sales, filters, and safe CSV', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name.includes('mobile'), 'Administrative reporting is covered on desktop.');
  await login(page, 'report-finance');
  await page.goto('/wp-admin/admin.php?page=compadres-sales-tax&period=month');

  await expect(page.getByRole('heading', { level: 1, name: 'Sales & Tax Dashboard' })).toBeVisible();
  await expect(page.getByText(/estimates for reconciliation, not a filed tax return/i)).toBeVisible();
  await expect(page.getByText(/2026 Avg Combined Reference by destination state/)).toBeVisible();
  await expect(page.getByText(/Shipping and tobacco excise taxes are not included/)).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Tax collected by destination state' })).toBeVisible();
  await expect(page.getByRole('row').filter({ hasText: 'IL' })).toBeVisible();
  await page.getByLabel('Product').selectOption({ label: 'Dashboard Test Cigar (DASHBOARD-TEST)' });
  await page.getByLabel('State').selectOption('MO');
  await page.getByRole('button', { name: 'Apply filters' }).click();
  await expect(page.getByRole('row').filter({ hasText: 'MO' })).toContainText('$7.00');
  await expect(page.getByRole('row').filter({ hasText: 'IL' })).toHaveCount(0);
  await expect(page.getByRole('row').filter({ hasText: 'Dashboard Test Cigar' })).toContainText('DASHBOARD-TEST');
  await expect(page.locator('body')).not.toContainText('@example.test');

  const exportUrl = await page.getByRole('link', { name: 'Export CSV' }).getAttribute('href');
  expect(exportUrl).toBeTruthy();
  const exportResponse = await page.request.get(exportUrl as string);
  expect(exportResponse.status()).toBe(200);
  expect(exportResponse.headers()['content-disposition']).toMatch(/compadres-sales-tax-\d{8}-\d{8}\.csv/);
  await expect(exportResponse.text()).resolves.toContain('State summary');
});

test('unauthorized users cannot open or export sales and tax reports', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name.includes('mobile'), 'Administrative reporting is covered on desktop.');
  await login(page, 'report-unauthorized');

  const pageResponse = await page.goto('/wp-admin/admin.php?page=compadres-sales-tax');
  expect(pageResponse?.status()).toBe(403);
  await expect(page.getByRole('heading', { name: 'Sales & Tax Dashboard' })).toHaveCount(0);

  const exportResponse = await page.request.get('/wp-admin/admin-post.php?action=compadres_export_sales_tax_report');
  expect(exportResponse.status()).toBe(403);
});

test('manual tax integrity rejects standard-class leaks and ignores unrelated product classifications', async ({}, testInfo) => {
  test.skip(testInfo.project.name.includes('mobile'), 'Manual tax runtime integrity is covered once on desktop.');
  const outcome = JSON.parse(
    wpCli([
      'eval',
      `
$leak = WC_Tax::_insert_tax_rate(array(
  'tax_rate_country' => 'US', 'tax_rate_state' => 'MO', 'tax_rate' => '8.4400',
  'tax_rate_name' => 'Compadres Avg Sales Tax', 'tax_rate_priority' => 1,
  'tax_rate_compound' => 0, 'tax_rate_shipping' => 0, 'tax_rate_order' => 0, 'tax_rate_class' => '',
));
$valid_with_leak = Compadres\\Commerce\\Tax\\ManualSalesTaxInstaller::isInstallationValid();
Compadres\\Commerce\\Tax\\ManualSalesTaxInstaller::maybeInstall();
$valid_after_repair = Compadres\\Commerce\\Tax\\ManualSalesTaxInstaller::isInstallationValid();
$leak_remains = false;
foreach (WC_Tax::get_rates_for_tax_class('') as $rate) {
  $leak_remains = $leak_remains || 'Compadres Avg Sales Tax' === (string) $rate->tax_rate_name;
}
$product = new WC_Product_Simple();
$product->set_name('Temporary unrelated tax probe');
$product->set_regular_price('10');
$product->set_tax_status('none');
$product->update_meta_data('_compadres_sales_tax_classification', 'unrelated-free-form-value');
$product_id = $product->save();
$filtered_status = apply_filters('woocommerce_product_get_tax_status', $product->get_tax_status(), $product);
$filtered_class = apply_filters('woocommerce_product_get_tax_class', $product->get_tax_class(), $product);
$order = wc_create_order();
$order->add_product($product, 1);
$order->set_shipping_country('US');
$order->set_shipping_state('XX');
$tax = new Compadres\\Commerce\\Tax\\ManualSalesTaxIntegration();
try {
  $tax->snapshotRule($order, array('shipping_country' => 'US', 'shipping_state' => 'XX'));
  $tax->snapshotAmount($order);
  $tax->validateOrderPayment($order);
  $unrelated_allowed = true;
} catch (Throwable $exception) {
  $unrelated_allowed = false;
}
$has_snapshot = '' !== (string) $order->get_meta('_compadres_sales_tax_rule_state');
$order->delete(true);
$product->delete(true);
echo wp_json_encode(compact('valid_with_leak', 'valid_after_repair', 'leak_remains', 'filtered_status', 'filtered_class', 'unrelated_allowed', 'has_snapshot'));
`,
    ]),
  ) as {
    valid_with_leak: boolean;
    valid_after_repair: boolean;
    leak_remains: boolean;
    filtered_status: string;
    filtered_class: string;
    unrelated_allowed: boolean;
    has_snapshot: boolean;
  };

  expect(outcome).toEqual({
    valid_with_leak: false,
    valid_after_repair: true,
    leak_remains: false,
    filtered_status: 'none',
    filtered_class: '',
    unrelated_allowed: true,
    has_snapshot: false,
  });
});
