<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Shipping\FedExApiClient;
use Compadres\Commerce\Shipping\FedExConfiguration;
use Compadres\Commerce\Shipping\FedExTrackingStatus;
use Compadres\Commerce\Shipping\ShippingProviderException;
use PHPUnit\Framework\TestCase;

final class FedExTrackingStatusTest extends TestCase {

	/** @return array<string, mixed> */
	private function response( string $code, string $text ): array {
		return array(
			'output' => array(
				'completeTrackResults' => array(
					array(
						'trackResults' => array(
							array(
								'latestStatusDetail' => array(
									'code'           => $code,
									'statusByLocale' => $text,
								),
							),
						),
					),
				),
			),
		);
	}

	public function test_parses_delivered_and_in_transit(): void {
		$delivered = FedExTrackingStatus::fromResponse( $this->response( 'DL', 'Delivered' ) );
		$transit   = FedExTrackingStatus::fromResponse( $this->response( 'IT', 'In transit' ) );

		self::assertTrue( $delivered->delivered() );
		self::assertFalse( $transit->delivered() );
		self::assertSame( 'In transit', $transit->text() );
	}

	public function test_flags_exceptions_for_attention(): void {
		self::assertTrue( FedExTrackingStatus::fromResponse( $this->response( 'DE', 'Delivery exception' ) )->needsAttention() );
		self::assertFalse( FedExTrackingStatus::fromResponse( $this->response( 'IT', 'In transit' ) )->needsAttention() );
	}

	public function test_unknown_tracking_number_is_a_status_not_an_error(): void {
		$status = FedExTrackingStatus::fromResponse(
			array( 'output' => array( 'completeTrackResults' => array( array( 'trackResults' => array( array( 'error' => array( 'code' => 'TRACKING.TRACKINGNUMBER.NOTFOUND' ) ) ) ) ) ) )
		);

		self::assertSame( FedExTrackingStatus::NOT_FOUND, $status->code() );
	}

	public function test_malformed_response_fails_closed(): void {
		$this->expectException( ShippingProviderException::class );
		FedExTrackingStatus::fromResponse( array( 'output' => array() ) );
	}

	public function test_client_authenticates_then_tracks(): void {
		$transport = new FakeFedExTransport(
			array(
				array(
					'status' => 200,
					'body'   => '{"access_token":"token-value"}',
				),
				array(
					'status' => 200,
					// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Deterministic fixture.
					'body'   => json_encode( $this->response( 'OD', 'On FedEx vehicle for delivery' ), JSON_THROW_ON_ERROR ),
				),
			)
		);
		$client    = new FedExApiClient(
			new FedExConfiguration( Environment::fromString( 'local' ), false, 'https://apis-sandbox.fedex.com', 'client-id', 'client-secret', '123456789', 'US', 'MO', '63101' ),
			$transport
		);

		$status = $client->track( '794644790138' );

		self::assertSame( 'OD', $status->code() );
		self::assertStringEndsWith( '/track/v1/trackingnumbers', $transport->requests()[1]['url'] );
		self::assertStringContainsString( '794644790138', $transport->requests()[1]['body'] );
	}

	public function test_client_rejects_invalid_tracking_numbers(): void {
		$client = new FedExApiClient(
			new FedExConfiguration( Environment::fromString( 'local' ), false, 'https://apis-sandbox.fedex.com', 'client-id', 'client-secret', '123456789', 'US', 'MO', '63101' ),
			new FakeFedExTransport( array() )
		);

		$this->expectException( ShippingProviderException::class );
		$client->track( 'x' );
	}
}
