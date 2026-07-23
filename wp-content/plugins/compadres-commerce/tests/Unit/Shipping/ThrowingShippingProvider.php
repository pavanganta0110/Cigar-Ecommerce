<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\ShippingContext;
use Compadres\Commerce\Shipping\ShippingMethodProvider;
use RuntimeException;

final class ThrowingShippingProvider implements ShippingMethodProvider {

	public function isConfigured(): bool {
		return true;
	}

	public function eligibleServices( ShippingContext $context ): array {
		throw new RuntimeException( 'raw provider exception secret' );
	}

	public function supportsAdultSignature( string $service_id ): bool {
		throw new RuntimeException( 'raw provider exception secret' );
	}

	public function providerName(): string {
		return str_repeat( 'provider-', 30 );
	}

	public function serviceReference( string $service_id ): ?string {
		throw new RuntimeException( 'raw provider exception secret' );
	}
}
