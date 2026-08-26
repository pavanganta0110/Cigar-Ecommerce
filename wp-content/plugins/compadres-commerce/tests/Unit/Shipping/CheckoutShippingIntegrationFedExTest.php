<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\CheckoutShippingIntegration;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class CheckoutShippingIntegrationFedExTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! class_exists( 'WC_Shipping_Method' ) ) {
			class_alias( WooCommerceShippingMethodTestDouble::class, 'WC_Shipping_Method' );
		}
	}

	public function test_accepts_only_bounded_compadres_mock_or_fedex_rate_ids(): void {
		$integration = new CheckoutShippingIntegration();
		$method      = new ReflectionMethod( $integration, 'serviceIdFromRate' );

		self::assertSame( 'compadres_mock_eligible', $method->invoke( $integration, 'compadres_mock_shipping:7:compadres_mock_eligible' ) );
		self::assertSame( 'fedex_ground', $method->invoke( $integration, 'compadres_fedex_shipping:12:fedex_ground' ) );
		self::assertSame( '', $method->invoke( $integration, 'attacker_shipping:12:fedex_ground' ) );
		self::assertSame( '', $method->invoke( $integration, 'compadres_fedex_shipping:x:fedex_ground' ) );
		self::assertSame( '', $method->invoke( $integration, 'compadres_fedex_shipping:12:FEDEX GROUND' ) );
	}
}
