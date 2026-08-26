<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Reporting;

use WC_Order;

/** Loads HPOS-compatible WooCommerce orders in bounded pages. */
final class WooCommerceSalesReportRepository {

	public function __construct( private ?WooCommerceOrderReportMapper $mapper = null ) {
		$this->mapper ??= new WooCommerceOrderReportMapper();
	}

	public function report( ReportFilters $filters ): SalesTaxReport {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return SalesTaxReport::fromOrders( array() );
		}
		$snapshots = array();
		$seen      = array();
		$page      = 1;
		do {
			$result    = \wc_get_orders(
				array(
					'status'       => array( 'wc-processing', 'wc-completed', 'wc-refunded' ),
					'date_created' => $filters->from()->getTimestamp() . '...' . $filters->to()->getTimestamp(),
					'limit'        => 100,
					'page'         => $page,
					'paginate'     => true,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'return'       => 'objects',
				)
			);
			$orders    = is_object( $result ) && isset( $result->orders ) && is_array( $result->orders )
				? $result->orders
				: ( is_array( $result ) ? $result : array() );
			$max_pages = is_object( $result ) && isset( $result->max_num_pages ) ? max( 1, (int) $result->max_num_pages ) : 1;
			foreach ( $orders as $order ) {
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				$seen[ $order->get_id() ] = true;
				$snapshot                 = $this->mapper->map( $order, $filters->from(), $filters->to() );
				if ( '' !== $filters->state() && strtoupper( $snapshot->state() ) !== $filters->state() ) {
					continue;
				}
				$snapshots[] = $snapshot;
			}
			++$page;
		} while ( $page <= $max_pages );

		$page = 1;
		do {
			$result    = \wc_get_orders(
				array(
					'type'         => 'shop_order_refund',
					'date_created' => $filters->from()->getTimestamp() . '...' . $filters->to()->getTimestamp(),
					'limit'        => 100,
					'page'         => $page,
					'paginate'     => true,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'return'       => 'objects',
				)
			);
			$refunds   = is_object( $result ) && isset( $result->orders ) && is_array( $result->orders )
				? $result->orders
				: ( is_array( $result ) ? $result : array() );
			$max_pages = is_object( $result ) && isset( $result->max_num_pages ) ? max( 1, (int) $result->max_num_pages ) : 1;
			foreach ( $refunds as $refund ) {
				if ( ! is_object( $refund ) || ! method_exists( $refund, 'get_parent_id' ) ) {
					continue;
				}
				$parent_id = (int) $refund->get_parent_id();
				if ( $parent_id <= 0 || isset( $seen[ $parent_id ] ) ) {
					continue;
				}
				$order = wc_get_order( $parent_id );
				if ( ! $order instanceof WC_Order || ! in_array( 'wc-' . $order->get_status(), array( 'wc-processing', 'wc-completed', 'wc-refunded' ), true ) ) {
					continue;
				}
				$seen[ $parent_id ] = true;
				$snapshot           = $this->mapper->map( $order, $filters->from(), $filters->to(), false );
				if ( '' !== $filters->state() && strtoupper( $snapshot->state() ) !== $filters->state() ) {
					continue;
				}
				$snapshots[] = $snapshot;
			}
			++$page;
		} while ( $page <= $max_pages );
		return SalesTaxReport::fromOrders( $snapshots, $filters->productId() );
	}
}
