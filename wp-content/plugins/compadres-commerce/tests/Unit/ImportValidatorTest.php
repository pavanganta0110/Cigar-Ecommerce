<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Catalog\ImportValidator;
use PHPUnit\Framework\TestCase;

final class ImportValidatorTest extends TestCase {

	public function testReportsEveryInvalidRowWithoutSilentlyImportingIt(): void {
		$result = ImportValidator::validateRows(
			array(
				array(
					'product_name' => 'Fictional Robusto',
					'sku'          => 'DEV-1',
					'brand'        => 'Fictional Brand',
					'product_type' => 'simple',
					'upc'          => '012345678905',
				),
				array(
					'product_name' => '',
					'sku'          => 'DEV-2',
					'brand'        => '',
					'product_type' => 'unknown',
					'upc'          => 'bad',
				),
			)
		);

		self::assertCount( 1, $result->validRows() );
		self::assertCount( 1, $result->errors() );
		self::assertSame( 2, $result->errors()[0]['row'] );
		self::assertContains( 'product_name is required', $result->errors()[0]['messages'] );
	}
}
