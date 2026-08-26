<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Reporting;

use DateTimeImmutable;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;

/** Maps WooCommerce orders to privacy-minimized reporting snapshots. */
final class WooCommerceOrderReportMapper {

	public function map( WC_Order $order, ?DateTimeImmutable $refund_from = null, ?DateTimeImmutable $refund_to = null, bool $include_order_values = true ): OrderReportSnapshot {
		$products       = array();
		$refunded_sales = 0.0;
		$refunds        = $this->refundMetrics( $order, $refund_from, $refund_to );
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product           = $item->get_product();
			$variation_id      = (int) $item->get_variation_id();
			$parent_product_id = (int) $item->get_product_id();
			$product_id        = $parent_product_id > 0
				? $parent_product_id
				: ( $variation_id > 0 ? $variation_id : ( false !== $product ? (int) $product->get_id() : 0 ) );
			$sku               = false !== $product ? (string) $product->get_sku() : '';
			$stock             = false !== $product ? $product->get_stock_quantity() : null;
			$item_refund       = $refunds['items'][ $item->get_id() ] ?? array(
				'quantity' => 0.0,
				'revenue'  => 0.0,
				'tax'      => 0.0,
			);
			$refunded_quantity = $item_refund['quantity'];
			$refunded_revenue  = $item_refund['revenue'];
			$refunded_tax      = $item_refund['tax'];
			$refunded_sales   += $refunded_revenue;
			$products[]        = new ProductReportSnapshot(
				$product_id,
				$sku,
				(string) $item->get_name(),
				$include_order_values ? max( 0.0, (float) $item->get_quantity() ) : 0.0,
				$include_order_values ? max( 0.0, (float) $item->get_subtotal() ) : 0.0,
				$include_order_values ? max( 0.0, (float) $item->get_total() ) : 0.0,
				$include_order_values ? max( 0.0, (float) $item->get_total_tax() ) : 0.0,
				$refunded_quantity,
				$refunded_revenue,
				$refunded_tax,
				is_numeric( $stock ) ? (int) $stock : null
			);
		}
		$state = trim( (string) $order->get_meta( '_compadres_sales_tax_rule_state' ) );
		if ( '' === $state ) {
			$state = trim( (string) $order->get_shipping_state() );
		}
		if ( '' === $state ) {
			$state = trim( (string) $order->get_billing_state() );
		}
		$tax_snapshot = $order->get_meta( '_compadres_sales_tax_amount' );
		$tax          = is_numeric( $tax_snapshot ) ? max( 0.0, (float) $tax_snapshot ) : max( 0.0, (float) $order->get_total_tax() );
		return new OrderReportSnapshot(
			(string) $order->get_status(),
			$state,
			$include_order_values ? max( 0.0, (float) $order->get_subtotal() ) : 0.0,
			$include_order_values ? max( 0.0, (float) $order->get_discount_total() ) : 0.0,
			$include_order_values ? max( 0.0, (float) $order->get_shipping_total() ) : 0.0,
			$refunds['shipping'],
			$include_order_values ? $tax : 0.0,
			$refunds['tax'],
			$refunded_sales,
			$products,
			$include_order_values,
			$refunds['unallocated']
		);
	}

	/**
	 * @return array{shipping:float,tax:float,unallocated:float,items:array<int,array{quantity:float,revenue:float,tax:float}>}
	 */
	private function refundMetrics( WC_Order $order, ?DateTimeImmutable $from, ?DateTimeImmutable $to ): array {
		if ( null === $from || null === $to ) {
			$items = array();
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				if ( $item instanceof WC_Order_Item_Product ) {
					$items[ $item->get_id() ] = array(
						'quantity' => abs( (float) $order->get_qty_refunded_for_item( $item->get_id() ) ),
						'revenue'  => max( 0.0, (float) $order->get_total_refunded_for_item( $item->get_id() ) ),
						'tax'      => $this->refundedTaxForItem( $order, $item ),
					);
				}
			}
			return array(
				'shipping'    => max( 0.0, (float) $order->get_total_shipping_refunded() ),
				'tax'         => max( 0.0, (float) $order->get_total_tax_refunded() ),
				'unallocated' => 0.0,
				'items'       => $items,
			);
		}

		$metrics = array(
			'shipping'    => 0.0,
			'tax'         => 0.0,
			'unallocated' => 0.0,
			'items'       => array(),
		);
		foreach ( $order->get_refunds() as $refund ) {
			$date = $refund->get_date_created();
			if ( null === $date || $date->getTimestamp() < $from->getTimestamp() || $date->getTimestamp() > $to->getTimestamp() ) {
				continue;
			}
			$refund_tax          = abs( (float) $refund->get_total_tax() );
			$refund_shipping     = 0.0;
			$refund_line_revenue = 0.0;
			$metrics['tax']     += $refund_tax;
			foreach ( $refund->get_items( 'shipping' ) as $shipping_item ) {
				if ( $shipping_item instanceof WC_Order_Item_Shipping ) {
					$refund_shipping     += abs( (float) $shipping_item->get_total() );
					$metrics['shipping'] += abs( (float) $shipping_item->get_total() );
				}
			}
			foreach ( $refund->get_items( 'line_item' ) as $refund_item ) {
				if ( ! $refund_item instanceof WC_Order_Item_Product ) {
					continue;
				}
				$original_id = (int) $refund_item->get_meta( '_refunded_item_id', true );
				if ( $original_id <= 0 ) {
					continue;
				}
				if ( ! isset( $metrics['items'][ $original_id ] ) ) {
					$metrics['items'][ $original_id ] = array(
						'quantity' => 0.0,
						'revenue'  => 0.0,
						'tax'      => 0.0,
					);
				}
				$metrics['items'][ $original_id ]['quantity'] += abs( (float) $refund_item->get_quantity() );
				$metrics['items'][ $original_id ]['revenue']  += abs( (float) $refund_item->get_total() );
				$metrics['items'][ $original_id ]['tax']      += abs( (float) $refund_item->get_total_tax() );
				$refund_line_revenue                          += abs( (float) $refund_item->get_total() );
			}
			$allocated               = $refund_line_revenue + $refund_shipping + $refund_tax;
			$metrics['unallocated'] += max( 0.0, abs( (float) $refund->get_amount() ) - $allocated );
		}
		return $metrics;
	}

	private function refundedTaxForItem( WC_Order $order, WC_Order_Item_Product $item ): float {
		$taxes   = $item->get_taxes();
		$tax_ids = isset( $taxes['total'] ) && is_array( $taxes['total'] ) ? array_keys( $taxes['total'] ) : array();
		$total   = 0.0;
		foreach ( $tax_ids as $tax_id ) {
			$total += abs( (float) $order->get_tax_refunded_for_item( $item->get_id(), (int) $tax_id ) );
		}
		return $total;
	}
}
