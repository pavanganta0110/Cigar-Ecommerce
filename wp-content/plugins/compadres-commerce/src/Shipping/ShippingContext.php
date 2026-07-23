<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/**
 * Normalized checkout/shipping context used by the Adult Signature Required policy.
 *
 * The selected shipping service is taken from the server-resolved chosen
 * shipping method, never from a browser-submitted eligibility claim.
 */
final class ShippingContext {

	private const EMPTY_COUNTRY = '';

	/**
	 * @param list<int> $product_ids
	 */
	public function __construct(
		private string $country,
		private string $state,
		private string $postal_code,
		private string $selected_service_id,
		private array $product_ids
	) {
	}

	public function country(): string {
		return $this->country;
	}

	public function state(): string {
		return $this->state;
	}

	public function postalCode(): string {
		return $this->postal_code;
	}

	public function selectedServiceId(): string {
		return $this->selected_service_id;
	}

	/** @return list<int> */
	public function productIds(): array {
		return $this->product_ids;
	}

	public function hasDestination(): bool {
		return self::EMPTY_COUNTRY !== $this->country && '' !== $this->state;
	}
}
