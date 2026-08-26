<?php

declare(strict_types=1);

namespace Compadres\Commerce\Checkout;

use JsonException;

/** Deterministic identity for a checkout attempt: cart contents, destination, and shipping selection. */
final class CartFingerprint {

	private function __construct( private string $value ) {}

	/**
	 * @param list<array{product_id?: mixed, variation_id?: mixed, quantity?: mixed}> $items
	 */
	public static function fromCart( array $items, string $country, string $state, string $postal_code, string $shipping_service ): self {
		$normalized = array();
		foreach ( $items as $item ) {
			$normalized[] = array(
				(int) ( $item['product_id'] ?? 0 ),
				(int) ( $item['variation_id'] ?? 0 ),
				round( (float) ( $item['quantity'] ?? 0 ), 4 ),
			);
		}
		sort( $normalized );
		$payload = array(
			'items'    => $normalized,
			'country'  => strtoupper( trim( $country ) ),
			'state'    => strtoupper( trim( $state ) ),
			'postal'   => strtoupper( trim( $postal_code ) ),
			'shipping' => strtolower( trim( $shipping_service ) ),
		);
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure, WordPress-independent value object; must stay unit-testable without the WP runtime.
			$encoded = json_encode( $payload, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			$encoded = '';
		}
		return new self( hash( 'sha256', $encoded ) );
	}

	public function value(): string {
		return $this->value;
	}

	public function equals( self $other ): bool {
		return hash_equals( $this->value, $other->value );
	}
}
