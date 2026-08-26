<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Reporting;

use Compadres\Commerce\Audit\AuditServiceFactory;
use Compadres\Commerce\Plugin;
use DateTimeImmutable;

/** Private sales, state-tax, refund, and product reporting dashboard. */
final class SalesTaxAdmin {

	public const CAPABILITY = 'compadres_view_tax_reports';
	public const PAGE_SLUG  = 'compadres-sales-tax';

	public function registerHooks(): void {
		add_action( 'admin_menu', array( $this, 'registerPage' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueStyles' ) );
		add_action( 'admin_post_compadres_export_sales_tax_report', array( $this, 'exportCsv' ) );
		add_filter( 'woocommerce_prevent_admin_access', array( $this, 'allowAuthorizedReportAccess' ) );
	}

	public function allowAuthorizedReportAccess( bool $prevent_access ): bool {
		return current_user_can( self::CAPABILITY ) ? false : $prevent_access;
	}

	public function registerPage(): void {
		add_menu_page(
			__( 'Compadres Sales and Tax', 'compadres-commerce' ),
			__( 'Sales & Tax', 'compadres-commerce' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'renderPage' ),
			'dashicons-chart-area',
			57
		);
	}

	public function enqueueStyles( string $hook_suffix ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}
		$plugin_file = dirname( __DIR__, 2 ) . '/compadres-commerce.php';
		wp_enqueue_style( 'compadres-sales-tax-admin', plugins_url( 'assets/css/sales-tax-admin.css', $plugin_file ), array(), Plugin::VERSION );
	}

	public function renderPage(): void {
		$this->authorize();
		$filters = $this->filters();
		$report  = ( new WooCommerceSalesReportRepository() )->report( $filters );
		$summary = $report->summary();
		?>
		<div class="wrap compadres-report">
			<h1><?php esc_html_e( 'Sales & Tax Dashboard', 'compadres-commerce' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Finalized WooCommerce orders only. Tax figures show net tax collected after recorded refunds; they are estimates for reconciliation, not a filed tax return or legal determination of tax liability.', 'compadres-commerce' ); ?>
			</p>
			<p class="compadres-tax-rule-notice">
				<strong><?php esc_html_e( 'Active checkout rule:', 'compadres-commerce' ); ?></strong>
				<?php esc_html_e( '2026 Avg Combined Reference by destination state, approved August 19, 2026. These are statewide average reference rates, not exact city or county rates. Shipping and tobacco excise taxes are not included in this rule set.', 'compadres-commerce' ); ?>
			</p>
			<?php $this->renderFilters( $filters ); ?>
			<div class="compadres-report-meta">
				<strong><?php esc_html_e( 'Reporting period:', 'compadres-commerce' ); ?></strong>
				<?php echo esc_html( $filters->from()->format( 'M j, Y' ) . ' – ' . $filters->to()->format( 'M j, Y' ) ); ?>
				<span aria-hidden="true"> • </span>
				<strong><?php esc_html_e( 'Generated:', 'compadres-commerce' ); ?></strong>
				<?php echo esc_html( wp_date( 'M j, Y g:i a' ) ); ?>
			</div>
			<?php $this->renderSummary( $summary ); ?>
			<?php $this->renderStateTable( $report->states() ); ?>
			<?php $this->renderProductTable( $report->products() ); ?>
		</div>
		<?php
	}

	public function exportCsv(): void {
		$this->authorize();
		check_admin_referer( 'compadres_export_sales_tax_report' );
		$filters = $this->filters();
		$report  = ( new WooCommerceSalesReportRepository() )->report( $filters );
		$content = SalesTaxCsv::render( $report->states(), $report->products() );
		AuditServiceFactory::create()->success(
			'report.sales_tax_exported',
			get_current_user_id(),
			'report',
			'sales_tax',
			array(
				'date_from'    => $filters->from()->format( 'Y-m-d' ),
				'date_to'      => $filters->to()->format( 'Y-m-d' ),
				'state'        => $filters->state(),
				'product_id'   => $filters->productId(),
				'state_rows'   => count( $report->states() ),
				'product_rows' => count( $report->products() ),
			)
		);
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="compadres-sales-tax-' . $filters->from()->format( 'Ymd' ) . '-' . $filters->to()->format( 'Ymd' ) . '.csv"' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated CSV download, formula-safe cells.
		exit;
	}

	private function renderFilters( ReportFilters $filters ): void {
		$export_args = array(
			'action'     => 'compadres_export_sales_tax_report',
			'period'     => $filters->preset(),
			'date_from'  => $filters->from()->format( 'Y-m-d' ),
			'date_to'    => $filters->to()->format( 'Y-m-d' ),
			'state'      => $filters->state(),
			'product_id' => $filters->productId(),
		);
		$export_url  = wp_nonce_url( add_query_arg( $export_args, admin_url( 'admin-post.php' ) ), 'compadres_export_sales_tax_report' );
		?>
		<form method="get" class="compadres-report-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
			<label for="compadres-period"><?php esc_html_e( 'Period', 'compadres-commerce' ); ?></label>
			<select id="compadres-period" name="period">
				<?php
				foreach ( array(
					'today'   => 'Today',
					'week'    => 'This week',
					'month'   => 'This month',
					'quarter' => 'This quarter',
					'year'    => 'This year',
					'custom'  => 'Custom',
				) as $value => $label ) :
					?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters->preset(), $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="compadres-date-from"><?php esc_html_e( 'From', 'compadres-commerce' ); ?></label>
			<input id="compadres-date-from" type="date" name="date_from" value="<?php echo esc_attr( $filters->from()->format( 'Y-m-d' ) ); ?>">
			<label for="compadres-date-to"><?php esc_html_e( 'To', 'compadres-commerce' ); ?></label>
			<input id="compadres-date-to" type="date" name="date_to" value="<?php echo esc_attr( $filters->to()->format( 'Y-m-d' ) ); ?>">
			<label for="compadres-state"><?php esc_html_e( 'State', 'compadres-commerce' ); ?></label>
			<select id="compadres-state" name="state"><option value=""><?php esc_html_e( 'All states', 'compadres-commerce' ); ?></option><?php $this->renderStateOptions( $filters->state() ); ?></select>
			<label for="compadres-product"><?php esc_html_e( 'Product', 'compadres-commerce' ); ?></label>
			<select id="compadres-product" name="product_id"><option value="0"><?php esc_html_e( 'All products', 'compadres-commerce' ); ?></option><?php $this->renderProductOptions( $filters->productId() ); ?></select>
			<?php submit_button( __( 'Apply filters', 'compadres-commerce' ), 'primary', 'filter_action', false ); ?>
			<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'compadres-commerce' ); ?></a>
		</form>
		<?php
	}

	/** @param array<string, int|float> $summary */
	private function renderSummary( array $summary ): void {
		$metrics = array(
			'net_collected'       => array( 'Net collected', true ),
			'net_sales'           => array( 'Net product sales', true ),
			'net_tax'             => array( 'Net tax collected', true ),
			'net_shipping'        => array( 'Net shipping', true ),
			'refunds'             => array( 'Product refunds', true ),
			'unallocated_refunds' => array( 'Unallocated refunds', true ),
			'orders'              => array( 'Finalized orders', false ),
			'net_units'           => array( 'Net units sold', false ),
		);
		?>
		<div class="compadres-report-cards">
			<?php foreach ( $metrics as $key => $metric ) : ?>
				<div class="compadres-report-card"><span><?php echo esc_html( $metric[0] ); ?></span><strong><?php echo $metric[1] ? wp_kses_post( wc_price( (float) $summary[ $key ] ) ) : esc_html( $this->quantity( (float) $summary[ $key ] ) ); ?></strong></div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/** @param list<array<string, mixed>> $states */
	private function renderStateTable( array $states ): void {
		?>
		<h2><?php esc_html_e( 'Tax collected by destination state', 'compadres-commerce' ); ?></h2>
		<div class="compadres-report-table"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'State', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Orders', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Gross sales', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Discounts', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Product refunds', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Unallocated refunds', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Net sales', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Net shipping', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Net tax collected', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Net collected', 'compadres-commerce' ); ?></th></tr></thead><tbody>
		<?php
		if ( array() === $states ) :
			?>
			<tr><td colspan="10"><?php esc_html_e( 'No finalized orders matched this period.', 'compadres-commerce' ); ?></td></tr><?php endif; ?>
		<?php
		foreach ( $states as $state ) :
			?>
			<tr><th scope="row"><?php echo esc_html( (string) $state['state'] ); ?></th><td><?php echo esc_html( (string) $state['orders'] ); ?></td>
			<?php
			foreach ( array( 'gross_sales', 'discounts', 'refunds', 'unallocated_refunds', 'net_sales', 'net_shipping', 'net_tax', 'net_collected' ) as $key ) :
				?>
			<td><?php echo wp_kses_post( wc_price( (float) $state[ $key ] ) ); ?></td><?php endforeach; ?></tr><?php endforeach; ?>
		</tbody></table></div>
		<?php
	}

	/** @param list<array<string, mixed>> $products */
	private function renderProductTable( array $products ): void {
		?>
		<h2><?php esc_html_e( 'Product sales', 'compadres-commerce' ); ?></h2>
		<div class="compadres-report-table"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Product', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'SKU', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Units', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Refunded units', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Net units', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Gross revenue', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Discounts', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Refunds', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Net revenue', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Net tax', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Current stock', 'compadres-commerce' ); ?></th></tr></thead><tbody>
		<?php
		if ( array() === $products ) :
			?>
			<tr><td colspan="11"><?php esc_html_e( 'No products matched this period.', 'compadres-commerce' ); ?></td></tr><?php endif; ?>
		<?php
		foreach ( $products as $product ) :
			?>
			<tr><th scope="row"><?php echo esc_html( (string) $product['name'] ); ?></th><td><?php echo esc_html( '' !== (string) $product['sku'] ? (string) $product['sku'] : '—' ); ?></td><td><?php echo esc_html( $this->quantity( (float) $product['units'] ) ); ?></td><td><?php echo esc_html( $this->quantity( (float) $product['refunded_units'] ) ); ?></td><td><?php echo esc_html( $this->quantity( (float) $product['net_units'] ) ); ?></td>
			<?php
			foreach ( array( 'gross_revenue', 'discounts', 'refunds', 'net_revenue', 'net_tax' ) as $key ) :
				?>
			<td><?php echo wp_kses_post( wc_price( (float) $product[ $key ] ) ); ?></td><?php endforeach; ?><td><?php echo esc_html( null === $product['stock_quantity'] ? 'Not tracked' : (string) $product['stock_quantity'] ); ?></td></tr><?php endforeach; ?>
		</tbody></table></div>
		<?php
	}

	private function renderStateOptions( string $selected_state ): void {
		$states = WC()->countries->get_states( 'US' );
		foreach ( $states as $code => $name ) {
			echo '<option value="' . esc_attr( (string) $code ) . '" ' . selected( $selected_state, $code, false ) . '>' . esc_html( (string) $name ) . '</option>';
		}
	}

	private function renderProductOptions( int $selected_product ): void {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return;
		}
		foreach ( wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => -1,
				'orderby' => 'name',
				'order'   => 'ASC',
				'return'  => 'objects',
			)
		) as $product ) {
			$label = (string) $product->get_name();
			$sku   = (string) $product->get_sku();
			if ( '' !== $sku ) {
				$label .= ' (' . $sku . ')';
			}
			echo '<option value="' . esc_attr( (string) $product->get_id() ) . '" ' . selected( $selected_product, $product->get_id(), false ) . '>' . esc_html( $label ) . '</option>';
		}
	}

	private function filters(): ReportFilters {
		$input = array();
		foreach ( array( 'period', 'date_from', 'date_to', 'state', 'product_id' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filter and nonce-protected export.
				$input[ $key ] = sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		return ReportFilters::fromInput( $input, new DateTimeImmutable( 'now', wp_timezone() ) );
	}

	private function authorize(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You are not allowed to view sales and tax reports.', 'compadres-commerce' ),
				esc_html__( 'Access denied', 'compadres-commerce' ),
				array( 'response' => 403 )
			);
		}
	}

	private function quantity( float $value ): string {
		return 0.0 === fmod( $value, 1.0 ) ? number_format_i18n( $value, 0 ) : number_format_i18n( $value, 2 );
	}
}
