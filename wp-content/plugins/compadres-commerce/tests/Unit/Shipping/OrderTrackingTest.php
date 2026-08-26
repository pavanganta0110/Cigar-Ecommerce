<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\OrderTracking;
use PHPUnit\Framework\TestCase;

final class OrderTrackingTest extends TestCase {

	public function test_sanitize_strips_non_alphanumeric_characters_and_uppercases(): void {
		self::assertSame( 'ABC123', OrderTracking::sanitize( ' abc-123 ' ) );
	}

	public function test_sanitize_bounds_length(): void {
		$long = str_repeat( '1', 50 );

		self::assertSame( 34, strlen( OrderTracking::sanitize( $long ) ) );
	}

	public function test_valid_tracking_numbers_are_accepted(): void {
		self::assertTrue( OrderTracking::isValid( '794658135984' ) );
	}

	public function test_too_short_tracking_numbers_are_rejected(): void {
		self::assertFalse( OrderTracking::isValid( '123' ) );
	}

	public function test_empty_tracking_number_is_rejected(): void {
		self::assertFalse( OrderTracking::isValid( '' ) );
	}

	public function test_tracking_url_is_null_for_an_invalid_number(): void {
		self::assertNull( OrderTracking::trackingUrl( '' ) );
		self::assertNull( OrderTracking::trackingUrl( '123' ) );
	}

	public function test_tracking_url_encodes_the_number_into_the_fedex_public_tracking_link(): void {
		$url = OrderTracking::trackingUrl( '794658135984' );

		self::assertSame( 'https://www.fedex.com/fedextrack/?trknbr=794658135984', $url );
	}
}
