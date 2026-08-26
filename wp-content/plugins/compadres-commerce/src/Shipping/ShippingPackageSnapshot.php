<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

use JsonException;

/** Immutable, non-PII package facts used for pay-for-order revalidation. */
final class ShippingPackageSnapshot {

	/** @param list<int> $product_ids */
	private function __construct(
		private array $product_ids,
		private float $weight,
		private string $weight_unit
	) {
	}

	public static function fromContext( ShippingContext $context ): ?self {
		return self::create( $context->productIds(), $context->weight(), $context->weightUnit() );
	}

	public static function fromJson( string $value ): ?self {
		if ( '' === $value || strlen( $value ) > 4096 ) {
			return null;
		}
		try {
			$decoded = json_decode( $value, true, 16, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			return null;
		}
		if ( ! is_array( $decoded ) ) {
			return null;
		}
		$product_ids = $decoded['product_ids'] ?? null;
		$weight      = $decoded['weight'] ?? null;
		$weight_unit = $decoded['weight_unit'] ?? null;
		if ( ! is_array( $product_ids ) || ( ! is_int( $weight ) && ! is_float( $weight ) ) || ! is_string( $weight_unit ) ) {
			return null;
		}
		return self::create( $product_ids, (float) $weight, $weight_unit );
	}

	public function toJson(): string {
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- JSON_THROW_ON_ERROR is required for an immutable fail-closed snapshot.
			return json_encode(
				array(
					'product_ids' => $this->product_ids,
					'weight'      => $this->weight,
					'weight_unit' => $this->weight_unit,
				),
				JSON_THROW_ON_ERROR
			);
		} catch ( JsonException ) {
			return '';
		}
	}

	public function context( string $country, string $state, string $postal_code, string $selected_service_id ): ShippingContext {
		return new ShippingContext(
			$country,
			$state,
			$postal_code,
			$selected_service_id,
			$this->product_ids,
			$this->weight,
			$this->weight_unit
		);
	}

	/** @param array<mixed> $product_ids */
	private static function create( array $product_ids, float $weight, string $weight_unit ): ?self {
		if ( array() === $product_ids || count( $product_ids ) > 100 || $weight < 0 || $weight > 150 || ! is_finite( $weight ) ) {
			return null;
		}
		$normalized_ids = array();
		foreach ( $product_ids as $product_id ) {
			if ( ! is_int( $product_id ) || $product_id <= 0 ) {
				return null;
			}
			$normalized_ids[] = $product_id;
		}
		$unit = strtoupper( $weight_unit );
		if ( ! in_array( $unit, array( 'LB', 'KG' ), true ) ) {
			return null;
		}
		return new self( array_values( array_unique( $normalized_ids ) ), round( $weight, 4 ), $unit );
	}
}
