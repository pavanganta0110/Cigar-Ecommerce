<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Checkout;

use Compadres\Commerce\Checkout\CartFingerprint;
use PHPUnit\Framework\TestCase;

final class CartFingerprintTest extends TestCase {

	public function test_same_cart_and_destination_produce_equal_fingerprints(): void {
		$items  = array(
			array(
				'product_id'   => 10,
				'variation_id' => 0,
				'quantity'     => 2,
			),
		);
		$first  = CartFingerprint::fromCart( $items, 'us', 'mo', '64111', 'fedex_ground' );
		$second = CartFingerprint::fromCart( $items, 'US', 'MO', '64111', 'FedEx_Ground' );

		self::assertTrue( $first->equals( $second ) );
	}

	public function test_item_order_does_not_change_the_fingerprint(): void {
		$forward  = CartFingerprint::fromCart(
			array(
				array(
					'product_id'   => 1,
					'variation_id' => 0,
					'quantity'     => 1,
				),
				array(
					'product_id'   => 2,
					'variation_id' => 0,
					'quantity'     => 3,
				),
			),
			'US',
			'MO',
			'64111',
			'fedex_ground'
		);
		$reversed = CartFingerprint::fromCart(
			array(
				array(
					'product_id'   => 2,
					'variation_id' => 0,
					'quantity'     => 3,
				),
				array(
					'product_id'   => 1,
					'variation_id' => 0,
					'quantity'     => 1,
				),
			),
			'US',
			'MO',
			'64111',
			'fedex_ground'
		);

		self::assertTrue( $forward->equals( $reversed ) );
	}

	public function test_a_changed_quantity_changes_the_fingerprint(): void {
		$items = static fn ( float $quantity ): array => array(
			array(
				'product_id'   => 10,
				'variation_id' => 0,
				'quantity'     => $quantity,
			),
		);
		$one   = CartFingerprint::fromCart( $items( 1 ), 'US', 'MO', '64111', 'fedex_ground' );
		$two   = CartFingerprint::fromCart( $items( 2 ), 'US', 'MO', '64111', 'fedex_ground' );

		self::assertFalse( $one->equals( $two ) );
	}

	public function test_a_changed_destination_changes_the_fingerprint(): void {
		$items    = array(
			array(
				'product_id'   => 10,
				'variation_id' => 0,
				'quantity'     => 1,
			),
		);
		$missouri = CartFingerprint::fromCart( $items, 'US', 'MO', '64111', 'fedex_ground' );
		$kansas   = CartFingerprint::fromCart( $items, 'US', 'KS', '64111', 'fedex_ground' );

		self::assertFalse( $missouri->equals( $kansas ) );
	}

	public function test_a_changed_shipping_service_changes_the_fingerprint(): void {
		$items  = array(
			array(
				'product_id'   => 10,
				'variation_id' => 0,
				'quantity'     => 1,
			),
		);
		$ground = CartFingerprint::fromCart( $items, 'US', 'MO', '64111', 'fedex_ground' );
		$home   = CartFingerprint::fromCart( $items, 'US', 'MO', '64111', 'fedex_home_delivery' );

		self::assertFalse( $ground->equals( $home ) );
	}

	public function test_value_is_a_bounded_hex_string(): void {
		$fingerprint = CartFingerprint::fromCart( array(), 'US', 'MO', '64111', 'fedex_ground' );

		self::assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $fingerprint->value() );
	}
}
