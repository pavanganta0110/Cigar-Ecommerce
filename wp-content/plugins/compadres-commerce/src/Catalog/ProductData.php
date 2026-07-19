<?php

declare(strict_types=1);

namespace Compadres\Commerce\Catalog;

use InvalidArgumentException;

final class ProductData {

	public const TEXT_FIELDS = array(
		'product_line',
		'country_of_origin',
		'wrapper',
		'binder',
		'filler',
		'flavor_profile',
		'vitola',
		'sales_tax_classification',
		'excise_tax_classification',
		'restricted_jurisdictions',
		'future_odoo_id',
		'future_wholesale_id',
	);

	/**
	 * @param  array<string, mixed> $input Raw values.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ): array {
		$output = array();
		foreach ( self::TEXT_FIELDS as $field ) {
			$output[ $field ] = self::text( $input[ $field ] ?? '' );
		}

		$upc = trim( (string) ( $input['upc'] ?? '' ) );
		if ( '' !== $upc && ! preg_match( '/^[0-9]{8,14}$/', $upc ) ) {
			throw new InvalidArgumentException( 'UPC must contain 8 to 14 digits.' );
		}
		$output['upc'] = $upc;

		$strengths = array( '', 'mild', 'mild-medium', 'medium', 'medium-full', 'full' );
		$strength  = strtolower( trim( (string) ( $input['strength'] ?? '' ) ) );
		if ( ! in_array( $strength, $strengths, true ) ) {
			throw new InvalidArgumentException( 'Strength is not recognized.' );
		}
		$output['strength'] = $strength;

		$length = (float) ( $input['length'] ?? 0 );
		if ( $length < 0 || $length > 20 ) {
			throw new InvalidArgumentException( 'Length must be between 0 and 20 inches.' );
		}
		$output['length'] = 0.0 === $length ? '' : rtrim( rtrim( number_format( $length, 2, '.', '' ), '0' ), '.' );

		$ring_gauge = (int) ( $input['ring_gauge'] ?? 0 );
		if ( $ring_gauge < 0 || $ring_gauge > 100 ) {
			throw new InvalidArgumentException( 'Ring gauge must be between 0 and 100.' );
		}
		$output['ring_gauge'] = $ring_gauge;

		foreach ( array( 'pack_quantity', 'box_quantity' ) as $quantity ) {
			$value = (int) ( $input[ $quantity ] ?? 0 );
			if ( $value < 0 || $value > 1000 ) {
				throw new InvalidArgumentException( $quantity . ' must be between 0 and 1000.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$output[ $quantity ] = $value;
		}

		return $output;
	}

	private static function text( mixed $value ): string {
		return trim( strip_tags( (string) $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}
}
