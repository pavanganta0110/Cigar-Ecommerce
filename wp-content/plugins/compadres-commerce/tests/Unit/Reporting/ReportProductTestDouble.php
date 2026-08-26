<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Reporting;

final class ReportProductTestDouble {
	public function __construct( private int $id, private string $sku, private ?int $stock ) {
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_sku(): string {
		return $this->sku;
	}

	public function get_stock_quantity(): ?int {
		return $this->stock;
	}
}
