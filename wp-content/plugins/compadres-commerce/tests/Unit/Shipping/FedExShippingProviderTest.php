<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Shipping\FedExApiClient;
use Compadres\Commerce\Shipping\FedExConfiguration;
use Compadres\Commerce\Shipping\FedExShippingProvider;
use Compadres\Commerce\Shipping\ShippingContext;
use PHPUnit\Framework\TestCase;

final class FedExShippingProviderTest extends TestCase {

	public function test_exposes_only_adult_signature_rates_and_bounded_reference(): void {
		$transport = new FakeFedExTransport(
			array(
				array(
					'status' => 200,
					'body'   => '{"access_token":"token","expires_in":3600}',
				),
				array(
					'status' => 200,
					'body'   => '{"transactionId":"transaction-123","output":{"rateReplyDetails":[{"serviceType":"FEDEX_GROUND","serviceName":"FedEx Ground","signatureOptionType":"ADULT","ratedShipmentDetails":[{"rateType":"ACCOUNT","totalNetCharge":18.75,"shipmentRateDetail":{"currency":"USD"}}]}]}}',
				),
			)
		);
		$config    = new FedExConfiguration(
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
		$provider  = new FedExShippingProvider( $config, new FedExApiClient( $config, $transport ) );
		$context   = new ShippingContext( 'US', 'IL', '60601', 'fedex_ground', array( 10 ), 2.5, 'LB' );

		self::assertTrue( $provider->isConfigured() );
		self::assertSame( 'fedex', $provider->providerName() );
		self::assertSame( array( 'fedex_ground' ), $provider->eligibleServices( $context ) );
		self::assertTrue( $provider->supportsAdultSignature( 'fedex_ground' ) );
		self::assertSame( 'transaction-123:fedex_ground', $provider->serviceReference( 'fedex_ground' ) );
		self::assertCount( 1, $provider->rates( $context ) );
		self::assertCount( 2, $transport->requests(), 'Rates should be cached within the provider request.' );
	}

	public function test_unconfigured_provider_never_calls_fedex(): void {
		$transport = new FakeFedExTransport( array() );
		$config    = new FedExConfiguration(
			Environment::fromString( 'production' ),
			false,
			'https://apis.fedex.com',
			'',
			'',
			'',
			'US',
			'MO',
			'63101'
		);
		$provider  = new FedExShippingProvider( $config, new FedExApiClient( $config, $transport ) );

		self::assertFalse( $provider->isConfigured() );
		self::assertSame( array(), $provider->eligibleServices( new ShippingContext( 'US', 'IL', '60601', '', array( 10 ), 2.5, 'LB' ) ) );
		self::assertSame( array(), $transport->requests() );
	}
}
