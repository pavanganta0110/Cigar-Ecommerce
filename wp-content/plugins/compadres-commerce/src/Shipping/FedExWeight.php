<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/** Converts WooCommerce weight units to FedEx-supported units. */
final class FedExWeight {

	/** @return array{value: float, unit: string} */
	public static function normalize( float $value, string $unit ): array {
		if ( $value <= 0 || ! is_finite( $value ) ) {
			return self::emptyWeight();
		}
		switch ( strtolower( trim( $unit ) ) ) {
			case 'lb':
			case 'lbs':
				return array(
					'value' => round( $value, 2 ),
					'unit'  => 'LB',
				);
			case 'oz':
				return array(
					'value' => round( $value / 16, 2 ),
					'unit'  => 'LB',
				);
			case 'kg':
				return array(
					'value' => round( $value, 2 ),
					'unit'  => 'KG',
				);
			case 'g':
				return array(
					'value' => round( $value / 1000, 2 ),
					'unit'  => 'KG',
				);
			default:
				return self::emptyWeight();
		}
	}

	/** @return array{value: float, unit: string} */
	private static function emptyWeight(): array {
		return array(
			'value' => 0.0,
			'unit'  => 'LB',
		);
	}
}
