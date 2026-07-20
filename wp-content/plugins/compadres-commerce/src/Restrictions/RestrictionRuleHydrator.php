<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

use InvalidArgumentException;

final class RestrictionRuleHydrator {

	private const TARGET_KEYS = array(
		'state'         => 'state',
		'city'          => 'city',
		'postal_code'   => 'postal_code',
		'postal_prefix' => 'postal_prefix',
		'product_id'    => 'product_ids',
		'category_id'   => 'category_ids',
		'brand_id'      => 'brand_ids',
	);

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<RestrictionRule>
	 */
	public function hydrate( array $rows ): array {
		$grouped = array();
		foreach ( $rows as $row ) {
			$id          = (int) ( $row['id'] ?? 0 );
			$target_type = (string) ( $row['target_type'] ?? '' );
			if ( $id < 1 || ! isset( self::TARGET_KEYS[ $target_type ] ) ) {
				throw new InvalidArgumentException( 'Stored restriction rule data is invalid.' );
			}
			if ( ! isset( $grouped[ $id ] ) ) {
				$grouped[ $id ] = array(
					'id'              => $id,
					'name'            => (string) ( $row['name'] ?? '' ),
					'country'         => (string) ( $row['country'] ?? 'US' ),
					'blocked_message' => (string) ( $row['blocked_message'] ?? 'We cannot ship the current cart to this destination. Please update the shipping address or remove the restricted item.' ),
					'priority'        => (int) ( $row['priority'] ?? 0 ),
					'effective_at'    => (string) ( $row['effective_at'] ?? '' ) . '+00:00',
					'expires_at'      => empty( $row['expires_at'] ) ? null : (string) $row['expires_at'] . '+00:00',
				);
			}
			$key                      = self::TARGET_KEYS[ $target_type ];
			$grouped[ $id ][ $key ]   = $grouped[ $id ][ $key ] ?? array();
			$grouped[ $id ][ $key ][] = (string) ( $row['target_value'] ?? '' );
		}
		return array_values( array_map( static fn ( array $values ): RestrictionRule => RestrictionRule::fromArray( $values ), $grouped ) );
	}
}
