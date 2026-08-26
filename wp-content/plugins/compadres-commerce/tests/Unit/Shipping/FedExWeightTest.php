<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\FedExWeight;
use PHPUnit\Framework\TestCase;

final class FedExWeightTest extends TestCase {

	/** @dataProvider weightProvider */
	public function test_normalizes_woocommerce_weights( float $value, string $unit, float $expected, string $expected_unit ): void {
		$weight = FedExWeight::normalize( $value, $unit );

		self::assertSame( $expected, $weight['value'] );
		self::assertSame( $expected_unit, $weight['unit'] );
	}

	/** @return array<string, array{float, string, float, string}> */
	public function weightProvider(): array {
		return array(
			'pounds'    => array( 2.5, 'lbs', 2.5, 'LB' ),
			'ounces'    => array( 16.0, 'oz', 1.0, 'LB' ),
			'kilograms' => array( 2.5, 'kg', 2.5, 'KG' ),
			'grams'     => array( 1000.0, 'g', 1.0, 'KG' ),
		);
	}

	public function test_unknown_or_nonpositive_weight_fails_closed(): void {
		self::assertSame(
			array(
				'value' => 0.0,
				'unit'  => 'LB',
			),
			FedExWeight::normalize( 0.0, 'lbs' )
		);
		self::assertSame(
			array(
				'value' => 0.0,
				'unit'  => 'LB',
			),
			FedExWeight::normalize( 2.0, 'stone' )
		);
	}
}
