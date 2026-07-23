<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/**
 * Development-only mock implementation of the shipping-provider boundary.
 *
 * It returns deterministic scenarios and performs no real carrier calls.
 * It must never be used in production and is not carrier-approved.
 */
final class MockShippingProvider implements ShippingMethodProvider {

	private MockShippingScenario $scenario;

	public function __construct( MockShippingScenario $scenario ) {
		$this->scenario = $scenario;
	}

	public function isConfigured(): bool {
		return $this->scenario->isProviderAvailable();
	}

	public function eligibleServices( ShippingContext $context ): array {
		return $this->scenario->eligibleServiceIds();
	}

	public function supportsAdultSignature( string $service_id ): bool {
		return $this->scenario->supportsAdultSignature( $service_id );
	}

	public function providerName(): string {
		return 'mock';
	}

	public function serviceReference( string $service_id ): ?string {
		if ( '' === $service_id ) {
			return null;
		}
		return 'mock-' . $service_id;
	}
}
