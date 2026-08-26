<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Reporting;

use Compadres\Commerce\Reporting\ReportFilters;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class ReportFiltersTest extends TestCase {

	public function test_month_is_the_safe_default(): void {
		$filters = ReportFilters::fromInput( array(), new DateTimeImmutable( '2026-08-10 14:00:00', new DateTimeZone( 'America/Chicago' ) ) );

		self::assertSame( 'month', $filters->preset() );
		self::assertSame( '2026-08-01 00:00:00', $filters->from()->format( 'Y-m-d H:i:s' ) );
		self::assertSame( '2026-08-10 23:59:59', $filters->to()->format( 'Y-m-d H:i:s' ) );
		self::assertSame( '', $filters->state() );
		self::assertSame( 0, $filters->productId() );
	}

	public function test_custom_dates_state_and_product_are_normalized(): void {
		$filters = ReportFilters::fromInput(
			array(
				'period'     => 'custom',
				'date_from'  => '2026-07-01',
				'date_to'    => '2026-07-31',
				'state'      => ' mo ',
				'product_id' => '42',
			),
			new DateTimeImmutable( '2026-08-10', new DateTimeZone( 'America/Chicago' ) )
		);

		self::assertSame( 'custom', $filters->preset() );
		self::assertSame( '2026-07-01 00:00:00', $filters->from()->format( 'Y-m-d H:i:s' ) );
		self::assertSame( '2026-07-31 23:59:59', $filters->to()->format( 'Y-m-d H:i:s' ) );
		self::assertSame( 'MO', $filters->state() );
		self::assertSame( 42, $filters->productId() );
	}

	public function test_invalid_or_reversed_custom_range_falls_back_to_month(): void {
		$filters = ReportFilters::fromInput(
			array(
				'period'    => 'custom',
				'date_from' => '2026-08-11',
				'date_to'   => '2026-08-01',
				'state'     => 'Missouri',
			),
			new DateTimeImmutable( '2026-08-10', new DateTimeZone( 'UTC' ) )
		);

		self::assertSame( 'month', $filters->preset() );
		self::assertSame( '', $filters->state() );
	}
}
