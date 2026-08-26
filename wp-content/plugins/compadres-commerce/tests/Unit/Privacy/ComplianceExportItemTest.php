<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Privacy;

use Compadres\Commerce\Privacy\ComplianceExportItem;
use PHPUnit\Framework\TestCase;

final class ComplianceExportItemTest extends TestCase {

	public function test_it_builds_a_wordpress_export_item_shape(): void {
		$item = ComplianceExportItem::build( 501, '#501', array( 'age_status' => 'passed' ) );

		self::assertSame( ComplianceExportItem::GROUP_ID, $item['group_id'] );
		self::assertSame( ComplianceExportItem::GROUP_LABEL, $item['group_label'] );
		self::assertSame( 'compadres-order-501', $item['item_id'] );
		self::assertSame(
			array(
				array(
					'name'  => 'Order number',
					'value' => '#501',
				),
				array(
					'name'  => 'Age Status',
					'value' => 'passed',
				),
			),
			$item['data']
		);
	}

	public function test_it_humanizes_underscored_field_names(): void {
		$item = ComplianceExportItem::build( 1, '#1', array( 'shipping_eligibility_checked_at' => '2026-08-25T00:00:00+00:00' ) );

		self::assertSame( 'Shipping Eligibility Checked At', $item['data'][1]['name'] );
	}

	public function test_it_still_produces_the_order_number_row_with_no_compliance_fields(): void {
		$item = ComplianceExportItem::build( 1, '#1', array() );

		self::assertCount( 1, $item['data'] );
	}
}
