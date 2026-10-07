<?php

declare(strict_types=1);

namespace Compadres\Commerce\Shipping;

use Compadres\Commerce\Infrastructure\Environment;
use WC_Order;

/**
 * Keeps each shipped order's FedEx status current: right after staff records
 * a tracking number, and hourly for packages not yet delivered. Read-only
 * toward FedEx (status lookups); it never changes order status itself.
 */
final class TrackingStatusSync {

	public const CRON_HOOK = 'compadres_refresh_tracking_status';

	private const BATCH    = 40;
	private const LOOKBACK = '-60 days';

	public function registerHooks(): void {
		add_action( 'init', array( $this, 'ensureScheduled' ) );
		add_action( self::CRON_HOOK, array( $this, 'refreshOutstanding' ) );
		add_action( 'compadres_tracking_number_updated', array( $this, 'refreshOrder' ) );
	}

	public function ensureScheduled(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
		}
	}

	public function refreshOutstanding(): void {
		$client = $this->client();
		if ( null === $client ) {
			return;
		}
		$orders = wc_get_orders(
			array(
				'limit'        => self::BATCH,
				'orderby'      => 'date',
				'order'        => 'ASC',
				'date_created' => '>' . strtotime( self::LOOKBACK ),
				'status'       => array( 'wc-processing', 'wc-packed', 'wc-completed' ),
				'return'       => 'objects',
				'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded to a small batch within a 60-day window by a background job.
					'relation' => 'AND',
					array(
						'key'     => OrderTracking::META_KEY,
						'compare' => 'EXISTS',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => OrderTracking::META_STATUS_CODE,
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => OrderTracking::META_STATUS_CODE,
							'value'   => 'DL',
							'compare' => '!=',
						),
					),
				),
			)
		);
		foreach ( $orders as $order ) {
			$this->refresh( $client, $order );
		}
	}

	public function refreshOrder( int $order_id ): void {
		$client = $this->client();
		$order  = wc_get_order( $order_id );
		if ( null !== $client && $order instanceof WC_Order ) {
			$this->refresh( $client, $order );
		}
	}

	private function refresh( FedExApiClient $client, WC_Order $order ): void {
		$tracking = (string) $order->get_meta( OrderTracking::META_KEY );
		if ( ! OrderTracking::isValid( $tracking ) ) {
			return;
		}
		try {
			$status = $client->track( $tracking );
		} catch ( ShippingProviderException ) {
			return;
		}
		$previous = (string) $order->get_meta( OrderTracking::META_STATUS_TEXT );
		$order->update_meta_data( OrderTracking::META_STATUS_CODE, $status->code() );
		$order->update_meta_data( OrderTracking::META_STATUS_TEXT, $status->text() );
		$order->update_meta_data( OrderTracking::META_STATUS_CHECKED, (string) time() );
		if ( $previous !== $status->text() ) {
			/* translators: %s: FedEx status text. */
			$order->add_order_note( sprintf( __( 'FedEx status: %s', 'compadres-commerce' ), $status->text() ) );
		}
		$order->save();
	}

	private function client(): ?FedExApiClient {
		$environment   = Environment::fromString( (string) getenv( 'APP_ENV' ) );
		$configuration = FedExConfiguration::fromEnvironment( $environment );
		if ( ! $configuration->isConfigured() ) {
			return null;
		}
		return new FedExApiClient( $configuration, new WordPressFedExTransport() );
	}
}
