<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Privacy;

use Compadres\Commerce\Privacy\OrderComplianceMeta;
use PHPUnit\Framework\TestCase;

final class OrderComplianceMetaTest extends TestCase {

	public function test_it_keeps_only_scalar_compadres_prefixed_fields_with_the_prefix_stripped(): void {
		$fields = OrderComplianceMeta::scalarFields(
			array(
				'_compadres_age_status'       => 'passed',
				'_compadres_shipping_service' => 'fedex_ground',
				'_billing_first_name'         => 'Jane',
			)
		);

		self::assertSame(
			array(
				'age_status'       => 'passed',
				'shipping_service' => 'fedex_ground',
			),
			$fields
		);
	}

	public function test_it_excludes_date_of_birth_and_the_snapshot_keys(): void {
		$fields = OrderComplianceMeta::scalarFields(
			array(
				'_compadres_date_of_birth'          => '1990-01-01',
				'_compadres_order_snapshot'         => '{}',
				'_compadres_order_snapshot_version' => 1,
				'_compadres_age_status'             => 'passed',
			)
		);

		self::assertSame( array( 'age_status' => 'passed' ), $fields );
	}

	public function test_it_excludes_non_scalar_values(): void {
		$fields = OrderComplianceMeta::scalarFields(
			array(
				'_compadres_nested'     => array( 'a' => 1 ),
				'_compadres_age_status' => 'passed',
			)
		);

		self::assertSame( array( 'age_status' => 'passed' ), $fields );
	}

	public function test_an_empty_input_produces_an_empty_result(): void {
		self::assertSame( array(), OrderComplianceMeta::scalarFields( array() ) );
	}
}
