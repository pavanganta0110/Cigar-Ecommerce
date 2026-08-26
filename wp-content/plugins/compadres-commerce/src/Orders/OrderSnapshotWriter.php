<?php

declare(strict_types=1);

namespace Compadres\Commerce\Orders;

use Compadres\Commerce\Audit\AuditServiceFactory;
use Compadres\Commerce\Catalog\BrandTaxonomy;
use Throwable;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Writes the canonical order snapshot once, at order creation, after every
 * compliance module (age verification, restrictions, shipping, tax) has
 * already written its own order meta for this order.
 *
 * The snapshot is a historical record, not a compliance gate: unlike the
 * restriction/age/shipping/tax checkout hooks, a failure here is audited
 * and swallowed rather than raised, so a snapshotting defect can never
 * block an order from being placed.
 */
final class OrderSnapshotWriter {

	public function registerHooks(): void {
		// After every compliance module's own order-creation writes (4-7) and
		// tax's post-creation amount snapshot (10, on this same later action).
		add_action( 'woocommerce_checkout_order_created', array( $this, 'write' ), 50 );
	}

	public function write( WC_Order $order ): void {
		if ( '' !== (string) $order->get_meta( OrderSnapshotSchema::META_KEY ) ) {
			return;
		}
		try {
			$snapshot = $this->build( $order );
			$order->update_meta_data( OrderSnapshotSchema::META_KEY, $snapshot->toJson() );
			$order->update_meta_data( OrderSnapshotSchema::META_KEY_VERSION, (string) OrderSnapshotSchema::VERSION );
			$order->save_meta_data();
		} catch ( Throwable $exception ) {
			AuditServiceFactory::create()->failure(
				'order.snapshot_failed',
				$exception->getMessage(),
				get_current_user_id(),
				'order',
				(string) $order->get_id()
			);
		}
	}

	private function build( WC_Order $order ): OrderSnapshot {
		$lines = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$lines[] = $this->lineSnapshot( $item )->toArray();
		}
		return new OrderSnapshot(
			OrderSnapshotSchema::VERSION,
			$order->get_id(),
			current_time( 'c', true ),
			$order->get_customer_id() > 0 ? 'registered' : 'guest',
			$order->get_status(),
			$order->get_currency(),
			$this->subtotal( $order ),
			(float) $order->get_discount_total(),
			(float) $order->get_shipping_total(),
			(float) $order->get_total_tax(),
			(float) $order->get_total(),
			$lines,
			ComplianceMetaFilter::filter( $this->flatMeta( $order ) )
		);
	}

	/** @return array<string, mixed> */
	private function flatMeta( WC_Order $order ): array {
		$meta = array();
		foreach ( $order->get_meta_data() as $entry ) {
			$data = $entry->get_data();
			$key  = $data['key'] ?? null;
			if ( is_string( $key ) ) {
				$meta[ $key ] = $data['value'] ?? null;
			}
		}
		return $meta;
	}

	private function subtotal( WC_Order $order ): float {
		$subtotal = 0.0;
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$subtotal += (float) $item->get_subtotal();
			}
		}
		return $subtotal;
	}

	private function lineSnapshot( WC_Order_Item_Product $item ): OrderLineSnapshot {
		$product     = $item->get_product();
		$sku         = false !== $product ? (string) $product->get_sku() : '';
		$brand_terms = false !== $product ? wp_get_post_terms( $product->get_id(), BrandTaxonomy::TAXONOMY ) : array();
		$brand_names = array();
		$brand_slugs = array();
		if ( is_array( $brand_terms ) ) {
			foreach ( $brand_terms as $term ) {
				$brand_names[] = $term->name;
				$brand_slugs[] = $term->slug;
			}
		}
		return new OrderLineSnapshot(
			$item->get_product_id(),
			$item->get_variation_id(),
			$sku,
			$item->get_name(),
			$brand_names,
			$brand_slugs,
			(float) $item->get_quantity(),
			(float) $item->get_subtotal(),
			(float) $item->get_total()
		);
	}
}
