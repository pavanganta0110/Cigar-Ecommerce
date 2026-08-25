<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\FedExShippingMethod;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class FedExShippingMethodTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! class_exists( 'WC_Shipping_Method' ) ) {
			class_alias( WooCommerceShippingMethodTestDouble::class, 'WC_Shipping_Method' );
		}
	}

	public function test_builds_bounded_rate_id_without_overriding_parent_method(): void {
		$method     = new FedExShippingMethod( 12 );
		$reflection = new ReflectionMethod( $method, 'buildRateId' );

		self::assertTrue( $reflection->isPrivate() );
		self::assertSame( 'compadres_fedex_shipping:12:fedex_ground', $reflection->invoke( $method, 'fedex_ground' ) );
		self::assertSame( WooCommerceShippingMethodTestDouble::class, get_parent_class( $method ) );
	}
}
