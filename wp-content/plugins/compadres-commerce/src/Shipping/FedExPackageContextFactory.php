<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/** Builds a rating context only from WooCommerce's server-side package. */
final class FedExPackageContextFactory {

	/** @param array<string, mixed> $package */
	public static function fromPackage( array $package, string $store_weight_unit, string $selected_service_id = '' ): ShippingContext {
		$destination = isset( $package['destination'] ) && is_array( $package['destination'] )
			? $package['destination']
			: array();
		$contents    = isset( $package['contents'] ) && is_array( $package['contents'] )
			? $package['contents']
			: array();
		$product_ids = array();
		$total       = 0.0;
		$invalid     = array() === $contents;
		foreach ( $contents as $item ) {
			if ( ! is_array( $item ) ) {
				$invalid = true;
				continue;
			}
			$id = (int) ( $item['variation_id'] ?? 0 );
			if ( $id <= 0 ) {
				$id = (int) ( $item['product_id'] ?? 0 );
			}
			if ( $id > 0 ) {
				$product_ids[] = $id;
			}
			$product = $item['data'] ?? null;
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_weight' ) ) {
				$invalid = true;
				continue;
			}
			if ( method_exists( $product, 'needs_shipping' ) && ! $product->needs_shipping() ) {
				continue;
			}
			$weight   = $product->get_weight();
			$quantity = $item['quantity'] ?? 0;
			if ( ! is_numeric( $weight ) || ! is_numeric( $quantity ) || (float) $weight <= 0 || (float) $quantity <= 0 ) {
				$invalid = true;
				continue;
			}
			$total += (float) $weight * (float) $quantity;
		}
		$normalized = $invalid
			? array(
				'value' => 0.0,
				'unit'  => 'LB',
			)
			: FedExWeight::normalize( $total, $store_weight_unit );
		return new ShippingContext(
			(string) ( $destination['country'] ?? '' ),
			(string) ( $destination['state'] ?? '' ),
			(string) ( $destination['postcode'] ?? '' ),
			$selected_service_id,
			array_values( array_unique( $product_ids ) ),
			$normalized['value'],
			$normalized['unit']
		);
	}
}
