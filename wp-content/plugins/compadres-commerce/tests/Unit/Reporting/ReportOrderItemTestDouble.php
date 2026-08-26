<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Reporting;

final class ReportOrderItemTestDouble {
	public function __construct(
		private int $id,
		private int $product_id,
		private int $variation_id,
		private string $name,
		private float $quantity,
		private float $subtotal,
		private float $total,
		private float $tax,
		private ReportProductTestDouble $product,
		private int $refunded_item_id = 0
	) {
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_product_id(): int {
		return $this->product_id;
	}

	public function get_variation_id(): int {
		return $this->variation_id;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_quantity(): float {
		return $this->quantity;
	}

	public function get_subtotal(): float {
		return $this->subtotal;
	}

	public function get_total(): float {
		return $this->total;
	}

	public function get_total_tax(): float {
		return $this->tax;
	}

	/** @return array{subtotal:array<int, float>,total:array<int, float>} */
	public function get_taxes(): array {
		return array(
			'subtotal' => array( 1 => $this->tax ),
			'total'    => array( 1 => $this->tax ),
		);
	}

	public function get_product(): ReportProductTestDouble {
		return $this->product;
	}

	public function get_meta( string $key, bool $single = false ): int {
		unset( $single );
		return '_refunded_item_id' === $key ? $this->refunded_item_id : 0;
	}
}
