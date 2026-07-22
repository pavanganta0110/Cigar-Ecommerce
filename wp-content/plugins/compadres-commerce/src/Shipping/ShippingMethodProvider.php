<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/**
 * Minimal, replaceable shipping-provider boundary.
 *
 * This interface is the only contract a real carrier adapter must satisfy.
 * It deliberately does not invent UPS, FedEx, or USPS API behavior. A real
 * adapter is built later, only with approved documentation and credentials.
 */
interface ShippingMethodProvider {

	/**
	 * Whether the provider is configured and reachable for eligibility checks.
	 *
	 * When validation is required and this returns false, checkout fails closed.
	 */
	public function isConfigured(): bool;

	/**
	 * Service identifiers eligible for cigar shipments (which require an
	 * adult signature) for the resolved context.
	 *
	 * @return list<string>
	 */
	public function eligibleServices( ShippingContext $context ): array;

	/**
	 * Whether the given service supports Adult Signature Required.
	 */
	public function supportsAdultSignature( string $service_id ): bool;

	/**
	 * Stable provider name for order metadata and audit context only.
	 */
	public function providerName(): string;

	/**
	 * Opaque provider/service reference when available; never customer data.
	 */
	public function serviceReference( string $service_id ): ?string;
}
