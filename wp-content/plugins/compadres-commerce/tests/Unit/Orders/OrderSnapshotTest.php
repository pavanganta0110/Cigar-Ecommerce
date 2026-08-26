<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Orders;

use Compadres\Commerce\Orders\OrderLineSnapshot;
use Compadres\Commerce\Orders\OrderSnapshot;
use PHPUnit\Framework\TestCase;

final class OrderSnapshotTest extends TestCase {

	public function test_to_array_includes_every_field_and_nested_lines(): void {
		$line     = new OrderLineSnapshot( 10, 0, 'SKU-1', 'Robusto', array( 'Compadres' ), array( 'compadres' ), 2.0, 40.0, 40.0 );
		$snapshot = new OrderSnapshot( 1, 501, '2026-08-25T00:00:00+00:00', 'guest', 'processing', 'USD', 40.0, 0.0, 5.0, 3.2, 48.2, array( $line->toArray() ), array( 'age_status' => 'passed' ) );

		$data = $snapshot->toArray();

		self::assertSame( 1, $data['schema_version'] );
		self::assertSame( 501, $data['order_id'] );
		self::assertSame( 'guest', $data['customer_type'] );
		self::assertSame( 48.2, $data['total'] );
		self::assertSame( array( 'age_status' => 'passed' ), $data['compliance'] );
		self::assertCount( 1, $data['lines'] );
		self::assertSame( 'SKU-1', $data['lines'][0]['sku'] );
	}

	public function test_to_json_round_trips_through_to_array(): void {
		$snapshot = new OrderSnapshot( 1, 501, '2026-08-25T00:00:00+00:00', 'registered', 'completed', 'USD', 40.0, 0.0, 0.0, 0.0, 40.0, array(), array() );

		$decoded = json_decode( $snapshot->toJson(), true );

		self::assertSame( $snapshot->toArray(), $decoded );
	}
}
