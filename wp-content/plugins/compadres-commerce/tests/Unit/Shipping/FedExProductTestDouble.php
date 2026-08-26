<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

final class FedExProductTestDouble {
	public function __construct( private string $weight ) {
	}

	public function get_weight(): string {
		return $this->weight;
	}

	public function needs_shipping(): bool {
		return true;
	}
}
