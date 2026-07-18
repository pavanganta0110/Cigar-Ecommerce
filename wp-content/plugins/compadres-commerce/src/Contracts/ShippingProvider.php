<?php

declare(strict_types=1);

namespace Compadres\Commerce\Contracts;

interface ShippingProvider {

	/**
	 * Return eligible shipping rates.
	 *
	 * @param array<string, mixed> $shipment Shipment request.
	 * @return array<int, array<string, mixed>>
	 */
	public function rates( array $shipment ): array;
	/**
	 * Validate an address.
	 *
	 * @param array<string, scalar|null> $address Address fields.
	 * @return array<string, mixed>
	 */
	public function validateAddress( array $address ): array;
	public function connectionTest(): bool;
}
