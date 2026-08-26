<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/** Normalized FedEx rate safe for WooCommerce display and order metadata. */
final class FedExRateQuote {

	public function __construct(
		private string $service_id,
		private string $label,
		private float $amount,
		private string $currency,
		private bool $supports_adult_signature,
		private string $reference
	) {
	}

	public function serviceId(): string {
		return $this->service_id;
	}

	public function label(): string {
		return $this->label;
	}

	public function amount(): float {
		return $this->amount;
	}

	public function currency(): string {
		return $this->currency;
	}

	public function supportsAdultSignature(): bool {
		return $this->supports_adult_signature;
	}

	public function reference(): string {
		return $this->reference;
	}
}
