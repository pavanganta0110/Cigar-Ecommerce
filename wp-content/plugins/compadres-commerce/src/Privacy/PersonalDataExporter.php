<?php

declare(strict_types=1);

namespace Compadres\Commerce\Privacy;

use WC_Order;

/**
 * Surfaces the Compadres-specific compliance fields held about a customer's
 * own orders (age verification status, shipping eligibility, tax rule
 * applied) to WordPress's personal-data export tool.
 *
 * WooCommerce's own exporter already covers the standard order fields
 * (addresses, items, totals); this exporter adds only what this plugin
 * stores on top of that.
 */
final class PersonalDataExporter {

	private const PAGE_SIZE = 10;

	public function registerHooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'registerExporter' ) );
	}

	/**
	 * @param array<string, array{exporter_friendly_name: string, callback: callable}> $exporters
	 * @return array<string, array{exporter_friendly_name: string, callback: callable}>
	 */
	public function registerExporter( array $exporters ): array {
		$exporters['compadres-commerce'] = array(
			'exporter_friendly_name' => __( 'Compadres Commerce', 'compadres-commerce' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/** @return array{data: list<array<string, mixed>>, done: bool} */
	public function export( string $email_address, int $page = 1 ): array {
		$orders = wc_get_orders(
			array(
				'billing_email' => $email_address,
				'limit'         => self::PAGE_SIZE,
				'page'          => max( 1, $page ),
				'orderby'       => 'ID',
				'order'         => 'ASC',
				'return'        => 'objects',
			)
		);

		$items = array();
		foreach ( $orders as $order ) {
			$fields = OrderComplianceMeta::scalarFields( $this->flatMeta( $order ) );
			if ( array() === $fields ) {
				continue;
			}
			$items[] = ComplianceExportItem::build( $order->get_id(), $order->get_order_number(), $fields );
		}

		return array(
			'data' => $items,
			'done' => count( $orders ) < self::PAGE_SIZE,
		);
	}

	/** @return array<string, mixed> */
	private function flatMeta( WC_Order $order ): array {
		$meta = array();
		foreach ( $order->get_meta_data() as $entry ) {
			$data = $entry->get_data();
			$key  = $data['key'] ?? null;
			if ( is_string( $key ) ) {
				$meta[ $key ] = $data['value'] ?? null;
			}
		}
		return $meta;
	}
}
