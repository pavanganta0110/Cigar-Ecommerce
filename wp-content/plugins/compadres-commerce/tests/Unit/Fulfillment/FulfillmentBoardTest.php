<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Fulfillment;

use Compadres\Commerce\Fulfillment\FulfillmentBoard;
use Compadres\Commerce\Fulfillment\FulfillmentRecord;
use Compadres\Commerce\Fulfillment\FulfillmentStage;
use Compadres\Commerce\Fulfillment\OrderStatuses;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FulfillmentBoardTest extends TestCase {

	private function now(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-10-07 12:00:00+00:00' );
	}

	private function record( int $id, string $created, string $status, string $tracking = '', bool $delivered = false ): FulfillmentRecord {
		return new FulfillmentRecord( $id, (string) $id, new DateTimeImmutable( $created ), $status, 'Customer', 1, 100.0, $tracking, '', $delivered );
	}

	public function test_classifies_each_stage(): void {
		self::assertSame( FulfillmentStage::TO_PACK, FulfillmentStage::classify( 'processing', false, false ) );
		self::assertSame( FulfillmentStage::PACKED, FulfillmentStage::classify( OrderStatuses::PACKED, false, false ) );
		self::assertSame( FulfillmentStage::SHIPPED, FulfillmentStage::classify( OrderStatuses::PACKED, true, false ) );
		self::assertSame( FulfillmentStage::DELIVERED, FulfillmentStage::classify( 'processing', true, true ) );
		self::assertSame( FulfillmentStage::DELIVERED, FulfillmentStage::classify( 'completed', false, false ) );
		self::assertSame( FulfillmentStage::NONE, FulfillmentStage::classify( 'pending', false, false ) );
		self::assertSame( FulfillmentStage::NONE, FulfillmentStage::classify( 'cancelled', true, false ) );
	}

	public function test_counts_stages_and_received_windows(): void {
		$board = FulfillmentBoard::fromRecords(
			array(
				$this->record( 1, '2026-10-07 09:00:00+00:00', 'processing' ),
				$this->record( 2, '2026-10-05 09:00:00+00:00', OrderStatuses::PACKED ),
				$this->record( 3, '2026-10-01 09:00:00+00:00', 'processing', '794644790138' ),
				$this->record( 4, '2026-09-20 09:00:00+00:00', 'completed', '794644790139', true ),
				$this->record( 5, '2026-10-06 09:00:00+00:00', 'pending' ),
			),
			$this->now()
		);

		self::assertSame( 1, $board->count( FulfillmentStage::TO_PACK ) );
		self::assertSame( 1, $board->count( FulfillmentStage::PACKED ) );
		self::assertSame( 1, $board->count( FulfillmentStage::SHIPPED ) );
		self::assertSame( 1, $board->count( FulfillmentStage::DELIVERED ) );
		self::assertSame(
			array(
				'today' => 1,
				'week'  => 3,
				'month' => 4,
			),
			$board->received()
		);
	}

	public function test_to_ship_is_oldest_first_and_flags_orders_unpacked_over_a_day(): void {
		$board = FulfillmentBoard::fromRecords(
			array(
				$this->record( 1, '2026-10-07 09:00:00+00:00', 'processing' ),
				$this->record( 2, '2026-10-05 09:00:00+00:00', 'processing' ),
				$this->record( 3, '2026-10-06 09:00:00+00:00', OrderStatuses::PACKED ),
			),
			$this->now()
		);

		self::assertSame( array( 2, 3, 1 ), array_map( static fn ( FulfillmentRecord $r ): int => $r->orderId(), $board->toShip() ) );
		self::assertSame( 1, $board->staleToPack() );
	}

	public function test_packed_counts_as_a_finalized_sale(): void {
		self::assertContains( 'packed', OrderStatuses::finalized() );
		self::assertContains( 'wc-packed', OrderStatuses::finalizedPrefixed() );
	}
}
