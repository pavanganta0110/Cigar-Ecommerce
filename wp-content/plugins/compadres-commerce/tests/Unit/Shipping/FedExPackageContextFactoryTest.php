<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\FedExPackageContextFactory;
use PHPUnit\Framework\TestCase;

final class FedExPackageContextFactoryTest extends TestCase {

	public function test_builds_context_from_server_side_woocommerce_package(): void {
		$context = FedExPackageContextFactory::fromPackage(
			array(
				'destination' => array(
					'country'  => 'US',
					'state'    => 'IL',
					'postcode' => '60601',
				),
				'contents'    => array(
					array(
						'product_id'   => 10,
						'variation_id' => 0,
						'quantity'     => 2,
						'data'         => new FedExProductTestDouble( '8' ),
					),
				),
			),
			'oz',
			'fedex_ground'
		);

		self::assertSame( 'US', $context->country() );
		self::assertSame( 'IL', $context->state() );
		self::assertSame( '60601', $context->postalCode() );
		self::assertSame( 'fedex_ground', $context->selectedServiceId() );
		self::assertSame( array( 10 ), $context->productIds() );
		self::assertSame( 1.0, $context->weight() );
		self::assertSame( 'LB', $context->weightUnit() );
	}

	public function test_missing_product_weight_fails_closed(): void {
		$context = FedExPackageContextFactory::fromPackage(
			array(
				'destination' => array(
					'country'  => 'US',
					'state'    => 'IL',
					'postcode' => '60601',
				),
				'contents'    => array(
					array(
						'product_id' => 10,
						'quantity'   => 1,
						'data'       => new FedExProductTestDouble( '' ),
					),
				),
			),
			'lbs'
		);

		self::assertSame( 0.0, $context->weight() );
		self::assertSame( array( 10 ), $context->productIds() );
	}
}
