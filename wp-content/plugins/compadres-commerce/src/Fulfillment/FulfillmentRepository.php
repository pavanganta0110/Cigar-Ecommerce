<?php

declare(strict_types=1);

namespace Compadres\Commerce\Fulfillment;

use Compadres\Commerce\Shipping\OrderTracking;
use DateTimeImmutable;

/** Loads recent paid orders as lightweight fulfillment records (bounded). */
final class FulfillmentRepository {

	private const LOOKBACK = '-30 days';
	private const LIMIT    = 500;

	/** @return list<FulfillmentRecord> */
	public function recent(): array {
		$orders  = wc_get_orders(
			array(
				'limit'        => self::LIMIT,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => '>' . strtotime( self::LOOKBACK ),
				'status'       => array( 'wc-processing', 'wc-' . OrderStatuses::PACKED, 'wc-completed' ),
				'return'       => 'objects',
			)
		);
		$records = array();
		foreach ( $orders as $order ) {
			$created   = $order->get_date_created();
			$records[] = new FulfillmentRecord(
				$order->get_id(),
				$order->get_order_number(),
				null !== $created ? DateTimeImmutable::createFromInterface( $created ) : new DateTimeImmutable( 'now' ),
				$order->get_status(),
				trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				$order->get_item_count(),
				(float) $order->get_total(),
				(string) $order->get_meta( OrderTracking::META_KEY ),
				(string) $order->get_meta( OrderTracking::META_STATUS_TEXT ),
				'DL' === $order->get_meta( OrderTracking::META_STATUS_CODE )
			);
		}
		return $records;
	}
}
