<?php

declare(strict_types=1);

namespace Compadres\Commerce\Privacy;

use WC_Order;

/**
 * Reports the Compadres-specific compliance fields on a customer's orders
 * (age verification status, restriction/shipping/tax rule outcomes) as
 * retained rather than erasing them.
 *
 * These fields are the tobacco-sale, tax, and age-verification compliance
 * evidence this application exists to produce; deleting them on an erasure
 * request would remove the very record a future audit or tax authority
 * inquiry needs. This mirrors how the audit log is already documented:
 * retained records "may only be destroyed through a separately approved
 * procedure," not through the generic privacy-erasure tool. A real
 * retention schedule requires legal and privacy counsel review, which the
 * project's implementation plan already lists as an outstanding external
 * input; until that exists, explicitly reporting retention is the only
 * honest response, not a placeholder response.
 */
final class PersonalDataEraser {

	private const PAGE_SIZE = 10;

	public function registerHooks(): void {
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'registerEraser' ) );
	}

	/**
	 * @param array<string, array{eraser_friendly_name: string, callback: callable}> $erasers
	 * @return array<string, array{eraser_friendly_name: string, callback: callable}>
	 */
	public function registerEraser( array $erasers ): array {
		$erasers['compadres-commerce'] = array(
			'eraser_friendly_name' => __( 'Compadres Commerce', 'compadres-commerce' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/** @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool} */
	public function erase( string $email_address, int $page = 1 ): array {
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

		$items_retained = false;
		foreach ( $orders as $order ) {
			if ( array() !== OrderComplianceMeta::scalarFields( $this->flatMeta( $order ) ) ) {
				$items_retained = true;
			}
		}

		return array(
			'items_removed'  => false,
			'items_retained' => $items_retained,
			'messages'       => $items_retained
				? array(
					__( 'Compadres Commerce retains age-verification, restriction, shipping, and tax compliance records on this order as required tobacco-sale and tax evidence. These are not erased by this request.', 'compadres-commerce' ),
				)
				: array(),
			'done'           => count( $orders ) < self::PAGE_SIZE,
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
