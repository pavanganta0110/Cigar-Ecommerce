<?php

declare(strict_types=1);

namespace Compadres\Commerce\Privacy;

/**
 * Formats one order's compliance fields into a WordPress personal-data
 * export item.
 *
 * Deliberately free of any WordPress runtime call (including translation
 * functions, which are not available outside a bootstrapped WordPress
 * environment) so it stays unit-testable; the exporter that calls this
 * supplies WordPress-facing copy separately.
 */
final class ComplianceExportItem {

	public const GROUP_ID    = 'compadres_commerce_orders';
	public const GROUP_LABEL = 'Compadres Commerce compliance records';

	/**
	 * @param array<string, scalar> $fields
	 * @return array{group_id: string, group_label: string, item_id: string, data: list<array{name: string, value: string}>}
	 */
	public static function build( int $order_id, string $order_number, array $fields ): array {
		$data = array(
			array(
				'name'  => 'Order number',
				'value' => $order_number,
			),
		);
		foreach ( $fields as $key => $value ) {
			$data[] = array(
				'name'  => self::label( $key ),
				'value' => (string) $value,
			);
		}
		return array(
			'group_id'    => self::GROUP_ID,
			'group_label' => self::GROUP_LABEL,
			'item_id'     => 'compadres-order-' . $order_id,
			'data'        => $data,
		);
	}

	private static function label( string $key ): string {
		return ucwords( str_replace( '_', ' ', $key ) );
	}
}
