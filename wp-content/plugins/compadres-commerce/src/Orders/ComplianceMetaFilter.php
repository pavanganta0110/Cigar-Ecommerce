<?php

declare(strict_types=1);

namespace Compadres\Commerce\Orders;

/**
 * Selects the compliance meta a compliance module (age verification,
 * restrictions, shipping, tax) has already written to an order into a
 * single readable section of the order snapshot.
 *
 * This deliberately does not know the individual meta key names any
 * compliance module uses: every `_compadres_` order meta key is included by
 * key name (prefix stripped), so a compliance module added after this one
 * is picked up automatically without this class changing. Only exact,
 * case-sensitive matches against the excluded-key list are dropped; this is
 * a second, defense-in-depth backstop; a compliance module deleting
 * transient personal data (for example date of birth) before order
 * creation, as age verification already does, remains the sole authority
 * for what is safe to persist.
 */
final class ComplianceMetaFilter {

	private const PREFIX = '_compadres_';

	/** @var list<string> */
	private const EXCLUDED_KEYS = array(
		'_compadres_date_of_birth',
		'_compadres_order_snapshot',
		'_compadres_order_snapshot_version',
	);

	/**
	 * @param array<int|string, mixed> $meta
	 * @return array<string, mixed>
	 */
	public static function filter( array $meta ): array {
		$filtered = array();
		foreach ( $meta as $key => $value ) {
			if ( ! is_string( $key ) || ! str_starts_with( $key, self::PREFIX ) || in_array( $key, self::EXCLUDED_KEYS, true ) ) {
				continue;
			}
			$filtered[ substr( $key, strlen( self::PREFIX ) ) ] = $value;
		}
		ksort( $filtered );
		return $filtered;
	}
}
