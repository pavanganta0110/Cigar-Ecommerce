<?php

declare(strict_types=1);

namespace Compadres\Commerce\Fulfillment;

use DateTimeImmutable;

/** Counts and work lists for the operations dashboard. */
final class FulfillmentBoard {

	public const STALE_HOURS = 24;

	/**
	 * @param array<string, int>       $counts   Orders per stage.
	 * @param array<string, int>       $received Paid orders received per window.
	 * @param list<FulfillmentRecord>  $to_ship  To-pack and packed orders, oldest first.
	 * @param int                      $stale    To-pack orders waiting longer than STALE_HOURS.
	 */
	private function __construct(
		private array $counts,
		private array $received,
		private array $to_ship,
		private int $stale
	) {
	}

	/** @param list<FulfillmentRecord> $records */
	public static function fromRecords( array $records, DateTimeImmutable $now ): self {
		$counts   = array(
			FulfillmentStage::TO_PACK   => 0,
			FulfillmentStage::PACKED    => 0,
			FulfillmentStage::SHIPPED   => 0,
			FulfillmentStage::DELIVERED => 0,
		);
		$received = array(
			'today' => 0,
			'week'  => 0,
			'month' => 0,
		);
		$to_ship  = array();
		$stale    = 0;
		$today    = $now->setTime( 0, 0 );
		$week     = $now->modify( '-7 days' );
		$month    = $now->modify( '-30 days' );

		foreach ( $records as $record ) {
			$stage = $record->stage();
			if ( FulfillmentStage::NONE === $stage ) {
				continue;
			}
			++$counts[ $stage ];
			$created = $record->created();
			if ( $created >= $today ) {
				++$received['today'];
			}
			if ( $created >= $week ) {
				++$received['week'];
			}
			if ( $created >= $month ) {
				++$received['month'];
			}
			if ( in_array( $stage, array( FulfillmentStage::TO_PACK, FulfillmentStage::PACKED ), true ) ) {
				$to_ship[] = $record;
				if ( FulfillmentStage::TO_PACK === $stage && ( $now->getTimestamp() - $created->getTimestamp() ) > self::STALE_HOURS * 3600 ) {
					++$stale;
				}
			}
		}

		usort(
			$to_ship,
			static fn ( FulfillmentRecord $a, FulfillmentRecord $b ): int => $a->created() <=> $b->created()
		);

		return new self( $counts, $received, $to_ship, $stale );
	}

	public function count( string $stage ): int {
		return $this->counts[ $stage ] ?? 0;
	}

	/** @return array{today: int, week: int, month: int} */
	public function received(): array {
		return array(
			'today' => $this->received['today'],
			'week'  => $this->received['week'],
			'month' => $this->received['month'],
		);
	}

	/** @return list<FulfillmentRecord> */
	public function toShip(): array {
		return $this->to_ship;
	}

	public function staleToPack(): int {
		return $this->stale;
	}
}
