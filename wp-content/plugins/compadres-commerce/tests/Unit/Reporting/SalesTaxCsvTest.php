<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Reporting;

use Compadres\Commerce\Reporting\SalesTaxCsv;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SalesTaxCsvTest extends TestCase {

	public function test_exports_state_and_product_sections_and_blocks_spreadsheet_formulas(): void {
		$csv = SalesTaxCsv::render(
			array(
				array(
					'state'               => 'MO',
					'orders'              => 2,
					'gross_sales'         => 100.0,
					'discounts'           => 5.0,
					'refunds'             => 10.0,
					'unallocated_refunds' => 0.0,
					'net_sales'           => 85.0,
					'net_shipping'        => 8.0,
					'net_tax'             => 7.0,
					'net_collected'       => 100.0,
				),
			),
			array(
				array(
					'product_id'     => 1,
					'sku'            => '=DANGER',
					'name'           => '+Formula',
					'units'          => 2.0,
					'refunded_units' => 0.0,
					'net_units'      => 2.0,
					'gross_revenue'  => 100.0,
					'discounts'      => 5.0,
					'refunds'        => 0.0,
					'net_revenue'    => 95.0,
					'net_tax'        => 7.0,
					'stock_quantity' => 4,
				),
			)
		);

		self::assertStringContainsString( 'State summary', $csv );
		self::assertStringContainsString( 'Product summary', $csv );
		self::assertStringContainsString( "'=DANGER", $csv );
		self::assertStringContainsString( "'+Formula", $csv );
	}

	/**
	 * @dataProvider dangerousCellProvider
	 */
	public function test_neutralizes_formula_markers_after_whitespace_and_control_prefixes( string $cell ): void {
		$method = new ReflectionMethod( SalesTaxCsv::class, 'safeCell' );

		self::assertSame( "'" . $cell, $method->invoke( null, $cell ) );
	}

	/** @return iterable<string, array{string}> */
	public static function dangerousCellProvider(): iterable {
		yield 'leading spaces before formula' => array( '   =DANGER' );
		yield 'leading tab' => array( chr( 9 ) . 'DANGER' );
		yield 'leading carriage return' => array( "\rDANGER" );
		yield 'leading newline' => array( "\nDANGER" );
		yield 'controls before formula' => array( ' ' . chr( 9 ) . ' @DANGER' );
	}
}
