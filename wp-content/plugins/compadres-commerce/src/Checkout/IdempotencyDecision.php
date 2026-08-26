<?php

declare(strict_types=1);

namespace Compadres\Commerce\Checkout;

final class IdempotencyDecision {

	private function __construct(
		private bool $proceed,
		private ?int $existing_order_id,
		private string $reason
	) {}

	public static function proceed(): self {
		return new self( true, null, '' );
	}

	public static function duplicateOrder( int $order_id ): self {
		return new self( false, $order_id, 'duplicate_order' );
	}

	public static function locked(): self {
		return new self( false, null, 'locked' );
	}

	public function shouldProceed(): bool {
		return $this->proceed;
	}

	public function existingOrderId(): ?int {
		return $this->existing_order_id;
	}

	public function reason(): string {
		return $this->reason;
	}
}
