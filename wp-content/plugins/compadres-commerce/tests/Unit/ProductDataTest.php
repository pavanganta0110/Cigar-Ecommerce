<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Catalog\ProductData;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ProductDataTest extends TestCase {

	public function testValidatesAndNormalizesCigarMetadata(): void {
		$clean = ProductData::sanitize(
			array(
				'upc'            => '012345678905',
				'strength'       => 'medium-full',
				'length'         => '6.50',
				'ring_gauge'     => '52',
				'pack_quantity'  => '5',
				'flavor_profile' => ' Cocoa, cedar ',
				'future_odoo_id' => 'ODOO-100',
			)
		);

		self::assertSame( '012345678905', $clean['upc'] );
		self::assertSame( 'medium-full', $clean['strength'] );
		self::assertSame( '6.5', $clean['length'] );
		self::assertSame( 52, $clean['ring_gauge'] );
		self::assertSame( 5, $clean['pack_quantity'] );
		self::assertSame( 'Cocoa, cedar', $clean['flavor_profile'] );
	}

	public function testRejectsInvalidUpc(): void {
		$this->expectException( InvalidArgumentException::class );
		ProductData::sanitize( array( 'upc' => 'not-a-upc' ) );
	}

	public function testRejectsInvalidRingGauge(): void {
		$this->expectException( InvalidArgumentException::class );
		ProductData::sanitize( array( 'ring_gauge' => '200' ) );
	}
}
