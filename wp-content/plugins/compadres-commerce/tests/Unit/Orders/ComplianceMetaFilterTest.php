<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Orders;

use Compadres\Commerce\Orders\ComplianceMetaFilter;
use PHPUnit\Framework\TestCase;

final class ComplianceMetaFilterTest extends TestCase {

	public function test_it_keeps_only_compadres_prefixed_keys_with_the_prefix_stripped(): void {
		$filtered = ComplianceMetaFilter::filter(
			array(
				'_compadres_age_status'       => 'passed',
				'_compadres_shipping_service' => 'fedex_ground',
				'_billing_first_name'         => 'Jane',
				'_payment_method'             => 'cod',
			)
		);

		self::assertSame(
			array(
				'age_status'       => 'passed',
				'shipping_service' => 'fedex_ground',
			),
			$filtered
		);
	}

	public function test_it_excludes_date_of_birth_as_a_defense_in_depth_backstop(): void {
		$filtered = ComplianceMetaFilter::filter(
			array(
				'_compadres_date_of_birth' => '1990-01-01',
				'_compadres_age_status'    => 'passed',
			)
		);

		self::assertArrayNotHasKey( 'date_of_birth', $filtered );
		self::assertSame( 'passed', $filtered['age_status'] );
	}

	public function test_it_excludes_its_own_snapshot_keys_to_avoid_self_reference(): void {
		$filtered = ComplianceMetaFilter::filter(
			array(
				'_compadres_order_snapshot'         => '{"order_id":1}',
				'_compadres_order_snapshot_version' => 1,
				'_compadres_age_status'             => 'passed',
			)
		);

		self::assertSame( array( 'age_status' => 'passed' ), $filtered );
	}

	public function test_it_ignores_non_string_keys(): void {
		$filtered = ComplianceMetaFilter::filter(
			array(
				0                       => 'ignored',
				'_compadres_age_status' => 'passed',
			)
		);

		self::assertSame( array( 'age_status' => 'passed' ), $filtered );
	}

	public function test_an_empty_input_produces_an_empty_result(): void {
		self::assertSame( array(), ComplianceMetaFilter::filter( array() ) );
	}
}
