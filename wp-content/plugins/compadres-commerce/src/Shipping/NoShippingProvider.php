<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/**
 * Fallback provider used when no shipping provider is configured.
 *
 * It fails closed: no services are eligible and the provider is reported as
 * not configured, so checkout cannot proceed to payment.
 */
final class NoShippingProvider implements ShippingMethodProvider {

	public function isConfigured(): bool {
		return false;
	}

	public function eligibleServices( ShippingContext $context ): array {
		return array();
	}

	public function supportsAdultSignature( string $service_id ): bool {
		return false;
	}

	public function providerName(): string {
		return 'none';
	}

	public function serviceReference( string $service_id ): ?string {
		return null;
	}
}
