<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/**
 * Deterministic result of the Adult Signature Required shipping-eligibility check.
 *
 * The customer message is generic and must never expose provider internals,
 * service identifiers, or exception detail.
 */
final class ShippingEligibilityResult {

	public const REASON_OK                     = 'ok';
	public const REASON_PROVIDER_UNAVAILABLE   = 'provider_unavailable';
	public const REASON_NO_ELIGIBLE_SERVICE    = 'no_eligible_service';
	public const REASON_SERVICE_UNSUPPORTED    = 'service_unsupported';
	public const REASON_NO_SERVICE_SELECTED    = 'no_service_selected';
	public const REASON_INVALID_ORDER_SNAPSHOT = 'invalid_order_shipping_snapshot';

	private const MESSAGE = 'The selected shipping service cannot deliver cigars, which require an adult signature on delivery. Please choose an eligible shipping method or contact the store.';

	/**
	 * @param array<string, string> $audit_context Bounded, non-address fields only.
	 */
	private function __construct(
		private bool $requires_adult_signature,
		private bool $provider_configured,
		private string $selected_service_id,
		private bool $service_supports_adult_signature,
		private bool $eligible,
		private string $reason,
		private array $audit_context
	) {
	}

	/** @param array<string, string> $audit_context */
	public static function allowed(
		bool $requires_adult_signature,
		bool $provider_configured,
		string $selected_service_id,
		bool $service_supports_adult_signature,
		array $audit_context
	): self {
		return new self(
			$requires_adult_signature,
			$provider_configured,
			$selected_service_id,
			$service_supports_adult_signature,
			true,
			self::REASON_OK,
			$audit_context
		);
	}

	/** @param array<string, string> $audit_context */
	public static function blocked(
		bool $requires_adult_signature,
		bool $provider_configured,
		string $selected_service_id,
		bool $service_supports_adult_signature,
		string $reason,
		array $audit_context
	): self {
		return new self(
			$requires_adult_signature,
			$provider_configured,
			$selected_service_id,
			$service_supports_adult_signature,
			false,
			$reason,
			$audit_context
		);
	}

	public function requiresAdultSignature(): bool {
		return $this->requires_adult_signature;
	}

	public function providerConfigured(): bool {
		return $this->provider_configured;
	}

	public function selectedServiceId(): string {
		return $this->selected_service_id;
	}

	public function serviceSupportsAdultSignature(): bool {
		return $this->service_supports_adult_signature;
	}

	public function eligible(): bool {
		return $this->eligible;
	}

	public function reason(): string {
		return $this->reason;
	}

	/** @return array<string, string> */
	public function auditContext(): array {
		return $this->audit_context;
	}

	public function customerMessage(): string {
		return self::MESSAGE;
	}
}
