<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Reporting;

final class ReportOrderTestDouble {
	/** @param list<ReportRefundTestDouble> $refunds */
	public function __construct( private ReportOrderItemTestDouble $item, private array $refunds = array(), private array $meta = array() ) {
	}

	public function get_meta( string $key ): mixed {
		return $this->meta[ $key ] ?? '';
	}

	public function get_status(): string {
		return 'processing';
	}

	public function get_shipping_state(): string {
		return 'MO';
	}

	public function get_billing_state(): string {
		return 'IL';
	}

	public function get_subtotal(): float {
		return 100.0;
	}

	public function get_discount_total(): float {
		return 10.0;
	}

	public function get_shipping_total(): float {
		return 8.0;
	}

	public function get_total_tax(): float {
		return 9.0;
	}

	public function get_total_tax_refunded(): float {
		return 1.0;
	}

	public function get_total_shipping_refunded(): float {
		return 2.0;
	}

	/** @return array<int, ReportOrderItemTestDouble> */
	public function get_items( string $type = '' ): array {
		return 'line_item' === $type ? array( 5 => $this->item ) : array();
	}

	public function get_qty_refunded_for_item( int $item_id ): float {
		return 5 === $item_id ? -1.0 : 0.0;
	}

	public function get_total_refunded_for_item( int $item_id ): float {
		return 5 === $item_id ? 20.0 : 0.0;
	}

	public function get_tax_refunded_for_item( int $item_id, int $tax_id ): float {
		return 5 === $item_id && 1 === $tax_id ? -1.0 : 0.0;
	}

	/** @return list<ReportRefundTestDouble> */
	public function get_refunds(): array {
		return $this->refunds;
	}
}
