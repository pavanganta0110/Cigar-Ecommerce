<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Catalog\BrandData;
use PHPUnit\Framework\TestCase;

final class BrandDataTest extends TestCase {

	public function testSanitizesStructuredBrandValues(): void {
		$clean = BrandData::sanitize(
			array(
				'short_description'    => '  A fictional <b>brand</b>. ',
				'logo_attachment_id'   => '-4',
				'hero_attachment_id'   => '19',
				'accent_color'         => '#aB12f0',
				'featured_product_ids' => array( '7', 0, '11', '7' ),
				'display_order'        => '-2',
				'active'               => 'yes',
				'future_odoo_id'       => ' ODOO-42 ',
			)
		);

		self::assertSame( 'A fictional brand.', $clean['short_description'] );
		self::assertSame( 0, $clean['logo_attachment_id'] );
		self::assertSame( 19, $clean['hero_attachment_id'] );
		self::assertSame( '#ab12f0', $clean['accent_color'] );
		self::assertSame( array( 7, 11 ), $clean['featured_product_ids'] );
		self::assertSame( 0, $clean['display_order'] );
		self::assertTrue( $clean['active'] );
		self::assertSame( 'ODOO-42', $clean['future_odoo_id'] );
	}
}
