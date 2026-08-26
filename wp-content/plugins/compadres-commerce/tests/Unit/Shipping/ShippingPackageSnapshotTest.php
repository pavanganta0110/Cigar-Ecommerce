<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\ShippingContext;
use Compadres\Commerce\Shipping\ShippingPackageSnapshot;
use PHPUnit\Framework\TestCase;

final class ShippingPackageSnapshotTest extends TestCase {

	public function test_round_trips_immutable_non_pii_package_facts(): void {
		$snapshot = ShippingPackageSnapshot::fromContext(
			new ShippingContext( 'US', 'MO', '63101', 'fedex_ground', array( 12, 14 ), 2.75, 'LB' )
		);

		self::assertNotNull( $snapshot );
		$stored   = $snapshot->toJson();
		$restored = ShippingPackageSnapshot::fromJson( $stored );
		self::assertNotNull( $restored );
		$context = $restored->context( 'US', 'MO', '63101', 'fedex_ground' );
		self::assertSame( array( 12, 14 ), $context->productIds() );
		self::assertSame( 2.75, $context->weight() );
		self::assertSame( 'LB', $context->weightUnit() );
		self::assertStringNotContainsString( '63101', $stored );
	}

	/** @dataProvider invalidSnapshots */
	public function test_rejects_missing_or_invalid_package_facts( string $stored ): void {
		self::assertNull( ShippingPackageSnapshot::fromJson( $stored ) );
	}

	/** @return array<string, array{string}> */
	public static function invalidSnapshots(): array {
		return array(
			'missing'         => array( '' ),
			'malformed'       => array( '{' ),
			'empty products'  => array( '{"product_ids":[],"weight":1,"weight_unit":"LB"}' ),
			'negative weight' => array( '{"product_ids":[12],"weight":-1,"weight_unit":"LB"}' ),
			'unknown unit'    => array( '{"product_ids":[12],"weight":1,"weight_unit":"OZ"}' ),
		);
	}
}
