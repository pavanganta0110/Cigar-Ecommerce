<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Shipping\FedExApiClient;
use Compadres\Commerce\Shipping\FedExConfiguration;
use Compadres\Commerce\Shipping\ShippingContext;
use Compadres\Commerce\Shipping\ShippingProviderException;
use PHPUnit\Framework\TestCase;

final class FedExApiClientTest extends TestCase {

	public function test_authenticates_and_requests_adult_signature_account_rates(): void {
		$transport = new FakeFedExTransport(
			array(
				array(
					'status' => 200,
					'body'   => '{"access_token":"token-value","token_type":"bearer","expires_in":3600}',
				),
				array(
					'status' => 200,
					// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- JSON_THROW_ON_ERROR keeps the fixture deterministic.
					'body'   => json_encode(
						array(
							'transactionId' => 'transaction-123',
							'output'        => array(
								'rateReplyDetails' => array(
									array(
										'serviceType' => 'FEDEX_GROUND',
										'serviceName' => 'FedEx Ground',
										'signatureOptionType' => 'ADULT',
										'ratedShipmentDetails' => array(
											array(
												'rateType' => 'ACCOUNT',
												'totalNetCharge' => 18.75,
												'shipmentRateDetail' => array( 'currency' => 'USD' ),
											),
										),
									),
								),
							),
						),
						JSON_THROW_ON_ERROR
					),
				),
			)
		);
		$client    = new FedExApiClient( $this->configuration(), $transport );
		$rates     = $client->rates( new ShippingContext( 'US', 'IL', '60601', '', array( 10 ), 2.5, 'LB' ) );

		self::assertCount( 1, $rates );
		self::assertSame( 'fedex_ground', $rates[0]->serviceId() );
		self::assertSame( 'FedEx Ground', $rates[0]->label() );
		self::assertSame( 18.75, $rates[0]->amount() );
		self::assertSame( 'USD', $rates[0]->currency() );
		self::assertTrue( $rates[0]->supportsAdultSignature() );
		self::assertSame( 'transaction-123:fedex_ground', $rates[0]->reference() );

		$requests = $transport->requests();
		self::assertCount( 2, $requests );
		self::assertSame( 'https://apis.fedex.com/oauth/token', $requests[0]['url'] );
		parse_str( $requests[0]['body'], $auth_body );
		self::assertSame( 'client_credentials', $auth_body['grant_type'] );
		self::assertSame( 'client-id', $auth_body['client_id'] );
		self::assertSame( 'client-secret', $auth_body['client_secret'] );

		self::assertSame( 'https://apis.fedex.com/rate/v1/rates/quotes', $requests[1]['url'] );
		self::assertSame( 'Bearer token-value', $requests[1]['headers']['Authorization'] );
		$rate_body = json_decode( $requests[1]['body'], true, 32, JSON_THROW_ON_ERROR );
		self::assertSame( '123456789', $rate_body['accountNumber']['value'] );
		self::assertSame( '63101', $rate_body['requestedShipment']['shipper']['address']['postalCode'] );
		self::assertSame( '60601', $rate_body['requestedShipment']['recipient']['address']['postalCode'] );
		self::assertSame( 'ADULT', $rate_body['requestedShipment']['requestedPackageLineItems'][0]['packageSpecialServices']['signatureOptionType'] );
		self::assertContains( 'SIGNATURE_OPTION', $rate_body['requestedShipment']['requestedPackageLineItems'][0]['packageSpecialServices']['specialServiceTypes'] );
	}

	public function test_non_adult_or_malformed_rate_details_are_discarded(): void {
		$transport = new FakeFedExTransport(
			array(
				array(
					'status' => 200,
					'body'   => '{"access_token":"token-value","expires_in":3600}',
				),
				array(
					'status' => 200,
					'body'   => '{"transactionId":"tx","output":{"rateReplyDetails":[{"serviceType":"FEDEX_GROUND","serviceName":"Ground","signatureOptionType":"NO_SIGNATURE_REQUIRED","ratedShipmentDetails":[{"rateType":"ACCOUNT","totalNetCharge":10,"shipmentRateDetail":{"currency":"USD"}}]},{"serviceType":"EVIL SERVICE","signatureOptionType":"ADULT","ratedShipmentDetails":[]}]}}',
				),
			)
		);

		self::assertSame(
			array(),
			( new FedExApiClient( $this->configuration(), $transport ) )->rates(
				new ShippingContext( 'US', 'IL', '60601', '', array( 10 ), 2.5, 'LB' )
			)
		);
	}

	public function test_provider_errors_are_generic_and_do_not_expose_response_or_credentials(): void {
		$transport = new FakeFedExTransport(
			array(
				array(
					'status' => 401,
					'body'   => '{"errors":[{"message":"client-secret leaked"}]}',
				),
			)
		);

		try {
			( new FedExApiClient( $this->configuration(), $transport ) )->rates(
				new ShippingContext( 'US', 'IL', '60601', '', array( 10 ), 2.5, 'LB' )
			);
			self::fail( 'Expected a provider exception.' );
		} catch ( ShippingProviderException $exception ) {
			self::assertSame( 'FedEx authorization is unavailable.', $exception->getMessage() );
			self::assertStringNotContainsString( 'client-secret', $exception->getMessage() );
		}
	}

	private function configuration(): FedExConfiguration {
		return new FedExConfiguration(
			Environment::fromString( 'production' ),
			true,
			'https://apis.fedex.com',
			'client-id',
			'client-secret',
			'123456789',
			'US',
			'MO',
			'63101'
		);
	}
}
