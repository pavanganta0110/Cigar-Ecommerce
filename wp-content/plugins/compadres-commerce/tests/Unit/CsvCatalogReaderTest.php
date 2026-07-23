<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Catalog\CsvCatalogReader;
use Compadres\Commerce\Catalog\ImportErrorReport;
use PHPUnit\Framework\TestCase;

final class CsvCatalogReaderTest extends TestCase {

	public function testReadsBomHeaderAndReportsSourceRowNumbers(): void {
		$path = tempnam( sys_get_temp_dir(), 'compadres-csv-' );
		self::assertNotFalse( $path );
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Isolated PHPUnit temporary file.
			$path,
			"\xEF\xBB\xBFproduct_name,sku,brand,product_type,upc\nFictional Robusto,DEV-1,Fictional Brand,simple,012345678905\nBroken,DEV-2,,unknown,bad\n"
		);

		$result = ( new CsvCatalogReader() )->validateFile( $path );

		self::assertCount( 1, $result->validRows() );
		self::assertSame( 3, $result->errors()[0]['row'] );
		self::assertContains( 'brand is required', $result->errors()[0]['messages'] );
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes isolated PHPUnit temporary file.
	}

	public function testRejectsCsvWithoutRequiredHeaders(): void {
		$path = tempnam( sys_get_temp_dir(), 'compadres-csv-' );
		self::assertNotFalse( $path );
		file_put_contents( $path, "sku,brand\nDEV-1,Fictional Brand\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Isolated PHPUnit temporary file.

		$this->expectExceptionMessage( 'Missing required CSV columns: product_name, product_type' );
		try {
			( new CsvCatalogReader() )->validateFile( $path );
		} finally {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes isolated PHPUnit temporary file.
		}
	}

	public function testFormatsMachineReadableErrorReport(): void {
		$csv = ImportErrorReport::toCsv(
			array(
				array(
					'row'      => 3,
					'messages' => array( 'brand is required', 'UPC must contain 8 to 14 digits.' ),
				),
			)
		);

		self::assertSame( "row,errors\n3,\"brand is required; UPC must contain 8 to 14 digits.\"\n", $csv );
	}
}
