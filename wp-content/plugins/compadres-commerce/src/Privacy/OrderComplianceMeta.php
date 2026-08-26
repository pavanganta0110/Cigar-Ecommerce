<?php

declare(strict_types=1);

namespace Compadres\Commerce\Privacy;

/**
 * Selects the scalar `_compadres_` order meta safe to surface in a personal
 * data export: status codes, provider names, and rule/reference identifiers,
 * never a raw provider payload or a value a compliance module has already
 * decided is too sensitive to persist (for example date of birth, which age
 * verification deletes before order creation).
 *
 * Deliberately independent of Orders\ComplianceMetaFilter: exports and the
 * order snapshot serve different audiences (a customer's own request vs. an
 * internal historical record) and evolving one must not silently change
 * what the other exposes.
 */
final class OrderComplianceMeta {

	private const PREFIX = '_compadres_';

	/** @var list<string> */
	private const EXCLUDED_KEYS = array(
		'_compadres_date_of_birth',
		'_compadres_order_snapshot',
		'_compadres_order_snapshot_version',
	);

	/**
	 * @param array<int|string, mixed> $meta
	 * @return array<string, scalar>
	 */
	public static function scalarFields( array $meta ): array {
		$fields = array();
		foreach ( $meta as $key => $value ) {
			if ( ! is_string( $key ) || ! str_starts_with( $key, self::PREFIX ) || in_array( $key, self::EXCLUDED_KEYS, true ) ) {
				continue;
			}
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$fields[ substr( $key, strlen( self::PREFIX ) ) ] = $value;
		}
		ksort( $fields );
		return $fields;
	}
}
