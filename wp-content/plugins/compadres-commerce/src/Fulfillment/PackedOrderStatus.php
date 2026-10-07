<?php

declare(strict_types=1);

namespace Compadres\Commerce\Fulfillment;

use Compadres\Commerce\Audit\AuditServiceFactory;
use WC_Order;

/**
 * Adds a "Packed" order status between Processing and Completed so staff can
 * mark orders that are boxed and ready for a label, and so the dashboard can
 * show which paid orders are not packed yet.
 */
final class PackedOrderStatus {

	private const STATUS_KEY = 'wc-' . OrderStatuses::PACKED;
	private const ACTION     = 'mark_packed';
	private const CAPABILITY = 'edit_shop_orders';

	public function registerHooks(): void {
		add_action( 'init', array( $this, 'registerStatus' ) );
		add_filter( 'wc_order_statuses', array( $this, 'addToOrderStatuses' ) );
		add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'addToPaidStatuses' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'addBulkAction' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'addBulkAction' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handleBulkAction' ), 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handleBulkAction' ), 10, 3 );
		add_action( 'woocommerce_order_status_' . OrderStatuses::PACKED, array( $this, 'auditPacked' ), 10, 1 );
	}

	public function registerStatus(): void {
		register_post_status(
			self::STATUS_KEY,
			array(
				'label'                     => _x( 'Packed', 'Order status', 'compadres-commerce' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders. */
				'label_count'               => _n_noop( 'Packed <span class="count">(%s)</span>', 'Packed <span class="count">(%s)</span>', 'compadres-commerce' ),
			)
		);
	}

	/**
	 * @param array<string, string> $statuses
	 * @return array<string, string>
	 */
	public function addToOrderStatuses( array $statuses ): array {
		$result = array();
		foreach ( $statuses as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'wc-processing' === $key ) {
				$result[ self::STATUS_KEY ] = _x( 'Packed', 'Order status', 'compadres-commerce' );
			}
		}
		return $result;
	}

	/**
	 * @param list<string> $statuses
	 * @return list<string>
	 */
	public function addToPaidStatuses( array $statuses ): array {
		$statuses[] = OrderStatuses::PACKED;
		return $statuses;
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public function addBulkAction( array $actions ): array {
		$actions[ self::ACTION ] = __( 'Change status to packed', 'compadres-commerce' );
		return $actions;
	}

	/**
	 * @param string          $redirect_to Redirect URL.
	 * @param string          $action      Bulk action name.
	 * @param list<int|string> $ids         Selected order IDs.
	 */
	public function handleBulkAction( string $redirect_to, string $action, array $ids ): string {
		if ( self::ACTION !== $action || ! current_user_can( self::CAPABILITY ) ) {
			return $redirect_to;
		}
		$changed = 0;
		foreach ( $ids as $id ) {
			$order = wc_get_order( absint( $id ) );
			if ( ! $order instanceof WC_Order || ! in_array( $order->get_status(), array( 'processing', 'on-hold' ), true ) ) {
				continue;
			}
			$order->update_status( OrderStatuses::PACKED, __( 'Marked packed by staff.', 'compadres-commerce' ), true );
			++$changed;
		}
		return add_query_arg(
			array(
				'marked_packed' => $changed,
			),
			$redirect_to
		);
	}

	public function auditPacked( int $order_id ): void {
		AuditServiceFactory::create()->success( 'fulfillment.order_packed', get_current_user_id(), 'order', (string) $order_id );
	}
}
