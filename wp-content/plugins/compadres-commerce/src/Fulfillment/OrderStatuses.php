<?php

declare(strict_types=1);

namespace Compadres\Commerce\Fulfillment;

/** Order statuses this plugin adds or relies on, in one place. */
final class OrderStatuses {

	public const PACKED = 'packed';

	/**
	 * Statuses where the sale is paid and counts as a finalized sale
	 * (tax reports, "orders received"). Packed sits between processing and
	 * completed, so it must be included wherever processing is.
	 *
	 * @return list<string>
	 */
	public static function finalized(): array {
		return array( 'processing', self::PACKED, 'completed', 'refunded' );
	}

	/** @return list<string> WooCommerce-prefixed (`wc-`) versions of finalized(). */
	public static function finalizedPrefixed(): array {
		return array_map( static fn ( string $status ): string => 'wc-' . $status, self::finalized() );
	}
}
