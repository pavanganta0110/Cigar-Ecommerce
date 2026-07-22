<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\MockShippingMethod;
use Compadres\Commerce\Shipping\MockShippingScenario;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MockShippingMethodTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! class_exists( 'WC_Shipping_Method' ) ) {
			class_alias( WooCommerceShippingMethodTestDouble::class, 'WC_Shipping_Method' );
		}
	}

	public function test_builds_deterministic_rate_id_without_overriding_parent_method(): void {
		$method     = new MockShippingMethod( 7 );
		$reflection = new ReflectionMethod( $method, 'buildRateId' );

		self::assertTrue( $reflection->isPrivate() );
		self::assertSame(
			'compadres_mock_shipping:7:compadres_mock_eligible',
			$reflection->invoke( $method, MockShippingScenario::SERVICE_ELIGIBLE )
		);
		self::assertSame( WooCommerceShippingMethodTestDouble::class, get_parent_class( $method ) );
	}
}
