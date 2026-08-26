<?php

declare(strict_types=1);

namespace Compadres\Commerce\Shipping;

/**
 * A manually entered FedEx tracking number and its public tracking link.
 *
 * This is deliberately not automated: no code in this repository creates a
 * FedEx shipment or purchases a label (see docs/compliance.md, "Provider
 * boundary and production prohibition"). A staff member creates the label
 * in FedEx's own system as they would regardless, then records the
 * resulting tracking number here so the customer and staff have one place
 * to see it and a link to FedEx's own tracking page.
 */
final class OrderTracking {

	public const META_KEY = '_compadres_shipping_tracking_number';

	private const TRACKING_URL_BASE = 'https://www.fedex.com/fedextrack/?trknbr=';

	/** Bounded, uppercased, alphanumeric-only. FedEx tracking numbers are digits; letters are tolerated for other bounded free-text entry rather than rejected outright. */
	public static function sanitize( string $tracking_number ): string {
		$normalized = preg_replace( '/[^A-Za-z0-9]/', '', $tracking_number );
		$normalized = is_string( $normalized ) ? strtoupper( $normalized ) : '';
		return substr( $normalized, 0, 34 );
	}

	public static function isValid( string $tracking_number ): bool {
		return 1 === preg_match( '/^[A-Z0-9]{6,34}$/', $tracking_number );
	}

	public static function trackingUrl( string $tracking_number ): ?string {
		if ( ! self::isValid( $tracking_number ) ) {
			return null;
		}
		return self::TRACKING_URL_BASE . rawurlencode( $tracking_number );
	}
}
