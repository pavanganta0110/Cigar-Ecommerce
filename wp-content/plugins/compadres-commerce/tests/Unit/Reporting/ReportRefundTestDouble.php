<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Reporting;

use DateTimeImmutable;

final class ReportRefundTestDouble {
	public function __construct( private string $date, private ?ReportOrderItemTestDouble $item, private float $amount = 0.0 ) {
	}

	public function get_date_created(): DateTimeImmutable {
		return new DateTimeImmutable( $this->date );
	}

	public function get_total_tax(): float {
		return null !== $this->item ? $this->item->get_total_tax() : 0.0;
	}

	public function get_amount(): float {
		return $this->amount > 0.0 ? $this->amount : abs( null !== $this->item ? $this->item->get_total() + $this->item->get_total_tax() : 0.0 );
	}

	/** @return array<int, ReportOrderItemTestDouble> */
	public function get_items( string $type = '' ): array {
		return 'line_item' === $type && null !== $this->item ? array( $this->item->get_id() => $this->item ) : array();
	}
}
