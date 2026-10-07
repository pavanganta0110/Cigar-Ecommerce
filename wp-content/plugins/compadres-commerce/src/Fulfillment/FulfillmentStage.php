<?php

declare(strict_types=1);

namespace Compadres\Commerce\Fulfillment;

/** Where a paid order is in the pack-and-ship process. */
final class FulfillmentStage {

	public const TO_PACK   = 'to_pack';
	public const PACKED    = 'packed';
	public const SHIPPED   = 'shipped';
	public const DELIVERED = 'delivered';
	public const NONE      = 'none';

	/**
	 * @param string $status        WooCommerce status without the `wc-` prefix.
	 * @param bool   $has_tracking  A tracking number is recorded on the order.
	 * @param bool   $delivered     The carrier reports the package delivered.
	 */
	public static function classify( string $status, bool $has_tracking, bool $delivered ): string {
		if ( ! in_array( $status, array( 'processing', OrderStatuses::PACKED, 'completed' ), true ) ) {
			return self::NONE;
		}
		if ( $delivered ) {
			return self::DELIVERED;
		}
		if ( $has_tracking ) {
			return self::SHIPPED;
		}
		return match ( $status ) {
			'processing'          => self::TO_PACK,
			OrderStatuses::PACKED => self::PACKED,
			default               => self::DELIVERED,
		};
	}
}
