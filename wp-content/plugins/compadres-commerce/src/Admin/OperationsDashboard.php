<?php

declare(strict_types=1);

namespace Compadres\Commerce\Admin;

use Compadres\Commerce\AgeVerification\AgeVerificationSettings;
use Compadres\Commerce\AgeVerification\ProviderConfiguration;
use Compadres\Commerce\AgeVerification\VerificationStatus;
use Compadres\Commerce\AgeVerification\WordPressAgeVerificationRuntime;
use Compadres\Commerce\Fulfillment\FulfillmentBoard;
use Compadres\Commerce\Fulfillment\FulfillmentRecord;
use Compadres\Commerce\Fulfillment\FulfillmentRepository;
use Compadres\Commerce\Fulfillment\FulfillmentStage;
use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Integrations\IntegrationStatus;
use Compadres\Commerce\Payments\GlobalPaymentsConfiguration;
use Compadres\Commerce\Shipping\OrderShippingMeta;
use Compadres\Commerce\Shipping\OrderTracking;
use Compadres\Commerce\Shipping\WordPressShippingRuntime;
use DateTimeImmutable;
use WC_Order;
use WC_Order_Refund;

/**
 * A single landing page summarizing integration health and orders that need
 * staff attention, so nobody has to already know which settings screen or
 * order-list filter to open.
 *
 * Scoped to what is registered today: age verification, shipping, and
 * payments. A tax integration added later should add its own health
 * description and blocked-order query here rather than this page guessing
 * at an interface it does not yet know.
 */
final class OperationsDashboard {

	private const CAPABILITY  = 'compadres_view_audit_logs';
	private const PAGE        = 'compadres-operations';
	private const LOOKBACK    = '-30 days';
	private const RESULT_SIZE = 50;

	public function registerHooks(): void {
		add_action( 'admin_menu', array( $this, 'registerPage' ), 5 );
	}

	public function registerPage(): void {
		add_menu_page(
			__( 'Compadres Operations', 'compadres-commerce' ),
			__( 'Operations', 'compadres-commerce' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'renderPage' ),
			'dashicons-analytics',
			57
		);
	}

	public function renderPage(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to view operations status.', 'compadres-commerce' ) );
		}
		$statuses  = $this->integrationStatuses();
		$orders    = $this->attentionOrders();
		$shipments = $this->recentShipments();
		$refunds   = $this->recentRefunds();
		$now       = new DateTimeImmutable( 'now', wp_timezone() );
		$board     = FulfillmentBoard::fromRecords( ( new FulfillmentRepository() )->recent(), $now );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Compadres Operations', 'compadres-commerce' ); ?></h1>

			<h2><?php esc_html_e( 'Orders and fulfillment', 'compadres-commerce' ); ?></h2>
			<p class="compadres-board__received">
				<?php
				$received = $board->received();
				echo esc_html(
					sprintf(
						/* translators: 1: orders today, 2: orders in last 7 days, 3: orders in last 30 days. */
						__( 'Paid orders received: %1$d today, %2$d in the last 7 days, %3$d in the last 30 days.', 'compadres-commerce' ),
						$received['today'],
						$received['week'],
						$received['month']
					)
				);
				?>
			</p>
			<div class="compadres-board">
				<div class="compadres-board__card<?php echo $board->staleToPack() > 0 ? ' is-alert' : ''; ?>">
					<strong><?php echo esc_html( (string) $board->count( FulfillmentStage::TO_PACK ) ); ?></strong>
					<span><?php esc_html_e( 'Not packed yet', 'compadres-commerce' ); ?></span>
					<?php if ( $board->staleToPack() > 0 ) : ?>
						<em><?php echo esc_html( sprintf( /* translators: %d: orders. */ _n( '%d waiting over 24 hours', '%d waiting over 24 hours', $board->staleToPack(), 'compadres-commerce' ), $board->staleToPack() ) ); ?></em>
					<?php endif; ?>
				</div>
				<div class="compadres-board__card">
					<strong><?php echo esc_html( (string) $board->count( FulfillmentStage::PACKED ) ); ?></strong>
					<span><?php esc_html_e( 'Packed, needs a label', 'compadres-commerce' ); ?></span>
				</div>
				<div class="compadres-board__card">
					<strong><?php echo esc_html( (string) $board->count( FulfillmentStage::SHIPPED ) ); ?></strong>
					<span><?php esc_html_e( 'Shipped, in transit', 'compadres-commerce' ); ?></span>
				</div>
				<div class="compadres-board__card">
					<strong><?php echo esc_html( (string) $board->count( FulfillmentStage::DELIVERED ) ); ?></strong>
					<span><?php esc_html_e( 'Delivered (30 days)', 'compadres-commerce' ); ?></span>
				</div>
			</div>

			<h3><?php esc_html_e( 'Orders to pack and ship', 'compadres-commerce' ); ?></h3>
			<p><?php esc_html_e( 'Paid orders that are not shipped yet, oldest first. Mark orders as Packed from the Orders list (bulk action) or the order screen.', 'compadres-commerce' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Placed', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Items', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Total', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Status', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Waiting', 'compadres-commerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $board->toShip() ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'Nothing waiting to be packed or shipped.', 'compadres-commerce' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( array_slice( $board->toShip(), 0, 25 ) as $record ) : ?>
						<?php
						$hours = (int) floor( ( $now->getTimestamp() - $record->created()->getTimestamp() ) / 3600 );
						$late  = FulfillmentStage::TO_PACK === $record->stage() && $hours > FulfillmentBoard::STALE_HOURS;
						?>
						<tr<?php echo $late ? ' class="compadres-board__late"' : ''; ?>>
							<td><a href="<?php echo esc_url( $this->editUrl( $record ) ); ?>">#<?php echo esc_html( $record->orderNumber() ); ?></a></td>
							<td><?php echo esc_html( $record->created()->format( 'M j, g:i a' ) ); ?></td>
							<td><?php echo esc_html( $record->customerName() ); ?></td>
							<td><?php echo esc_html( (string) $record->itemCount() ); ?></td>
							<td><?php echo wp_kses_post( wc_price( $record->total() ) ); ?></td>
							<td><?php echo esc_html( FulfillmentStage::TO_PACK === $record->stage() ? __( 'Not packed', 'compadres-commerce' ) : __( 'Packed', 'compadres-commerce' ) ); ?></td>
							<td><?php echo esc_html( $hours >= 48 ? sprintf( /* translators: %d: days. */ _n( '%d day', '%d days', (int) floor( $hours / 24 ), 'compadres-commerce' ), (int) floor( $hours / 24 ) ) : sprintf( /* translators: %d: hours. */ _n( '%d hour', '%d hours', $hours, 'compadres-commerce' ), $hours ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Integration status', 'compadres-commerce' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Integration', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'State', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Production ready', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Detail', 'compadres-commerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $statuses as $status ) : ?>
						<tr>
							<td><?php echo esc_html( $status->integration() ); ?></td>
							<td><?php echo esc_html( $status->state() ); ?></td>
							<td><?php echo esc_html( $status->isProductionReady() ? __( 'Yes', 'compadres-commerce' ) : __( 'No', 'compadres-commerce' ) ); ?></td>
							<td><?php echo esc_html( $status->message() ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Orders needing attention', 'compadres-commerce' ); ?></h2>
			<p><?php esc_html_e( 'Orders from the last 30 days with a failed or unresolved age-verification result, or a blocked shipping-eligibility result.', 'compadres-commerce' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Date', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Age verification', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Shipping eligibility', 'compadres-commerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $orders ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No orders need attention.', 'compadres-commerce' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $orders as $order ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
							<td><?php echo esc_html( (string) $order->get_date_created() ); ?></td>
							<td><?php echo esc_html( (string) $order->get_meta( '_compadres_age_status' ) ); ?></td>
							<td><?php echo esc_html( (string) $order->get_meta( OrderShippingMeta::ELIGIBILITY ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Recent shipments', 'compadres-commerce' ); ?></h2>
			<p><?php esc_html_e( 'Orders from the last 30 days with a recorded tracking number.', 'compadres-commerce' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Date', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Tracking number', 'compadres-commerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $shipments ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No shipments recorded yet.', 'compadres-commerce' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $shipments as $order ) : ?>
						<?php
						$tracking     = (string) $order->get_meta( OrderTracking::META_KEY );
						$tracking_url = OrderTracking::trackingUrl( $tracking );
						?>
						<tr>
							<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
							<td><?php echo esc_html( (string) $order->get_date_created() ); ?></td>
							<td>
								<?php if ( null !== $tracking_url ) : ?>
									<a href="<?php echo esc_url( $tracking_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $tracking ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $tracking ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( '' !== (string) $order->get_meta( OrderTracking::META_STATUS_TEXT ) ? (string) $order->get_meta( OrderTracking::META_STATUS_TEXT ) : __( 'Not checked yet', 'compadres-commerce' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Recent refunds', 'compadres-commerce' ); ?></h2>
			<p><?php esc_html_e( 'Refunds created in the last 30 days. The refund itself is processed by the order\'s payment gateway; this table is a read-only record.', 'compadres-commerce' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Date', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'compadres-commerce' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'compadres-commerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $refunds ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No refunds recorded yet.', 'compadres-commerce' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $refunds as $refund ) : ?>
						<?php $parent = wc_get_order( $refund->get_parent_id() ); ?>
						<tr>
							<td>
								<?php if ( $parent instanceof WC_Order ) : ?>
									<a href="<?php echo esc_url( $parent->get_edit_order_url() ); ?>">#<?php echo esc_html( $parent->get_order_number() ); ?></a>
								<?php else : ?>
									<?php echo esc_html( (string) $refund->get_parent_id() ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) $refund->get_date_created() ); ?></td>
							<td><?php echo wp_kses_post( wc_price( (float) $refund->get_amount(), array( 'currency' => $refund->get_currency() ) ) ); ?></td>
							<td><?php echo esc_html( $refund->get_reason() ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function editUrl( FulfillmentRecord $record ): string {
		$order = wc_get_order( $record->orderId() );
		return $order instanceof WC_Order ? $order->get_edit_order_url() : '';
	}

	/** @return list<IntegrationStatus> */
	private function integrationStatuses(): array {
		$age_settings = get_option( WordPressAgeVerificationRuntime::OPTION, AgeVerificationSettings::defaults() );
		$age_config   = ProviderConfiguration::fromArray( is_array( $age_settings ) ? $age_settings : array() );
		$environment  = Environment::fromString( (string) getenv( 'APP_ENV' ) );
		return array(
			AgeVerificationHealth::describe( $age_config ),
			ShippingHealth::describe( ( new WordPressShippingRuntime() )->mockMethodAllowed() ),
			PaymentHealth::describe( GlobalPaymentsConfiguration::fromEnvironment( $environment ) ),
		);
	}

	/** @return list<WC_Order> */
	private function attentionOrders(): array {
		$orders = wc_get_orders(
			array(
				'limit'        => self::RESULT_SIZE,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => '>' . strtotime( self::LOOKBACK ),
				'return'       => 'objects',
				'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded to 50 results within a 30-day window on a dedicated operations page.
					'relation' => 'OR',
					array(
						'key'     => '_compadres_age_status',
						'value'   => array( VerificationStatus::FAILED, VerificationStatus::MANUAL_REVIEW, VerificationStatus::UNAVAILABLE ),
						'compare' => 'IN',
					),
					array(
						'key'   => OrderShippingMeta::ELIGIBILITY,
						'value' => OrderShippingMeta::ELIGIBILITY_BLOCKED,
					),
				),
			)
		);
		return $orders;
	}

	/** @return list<WC_Order> */
	private function recentShipments(): array {
		return wc_get_orders(
			array(
				'limit'        => self::RESULT_SIZE,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => '>' . strtotime( self::LOOKBACK ),
				'return'       => 'objects',
				'meta_key'     => OrderTracking::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded to 50 results within a 30-day window on a dedicated operations page.
				'meta_compare' => 'EXISTS',
			)
		);
	}

	/** @return list<WC_Order_Refund> */
	private function recentRefunds(): array {
		/** @var list<WC_Order_Refund> $refunds */
		$refunds = wc_get_orders(
			array(
				'type'         => 'shop_order_refund',
				'limit'        => self::RESULT_SIZE,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => '>' . strtotime( self::LOOKBACK ),
				'return'       => 'objects',
			)
		);
		return $refunds;
	}
}
