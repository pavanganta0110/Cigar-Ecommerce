<?php

declare(strict_types=1);

namespace Compadres\Commerce\Admin;

use Compadres\Commerce\Integrations\IntegrationStatus;

/**
 * Describes shipping integration health for the operations dashboard.
 *
 * Scoped to what a launch increment without an approved carrier adapter can
 * honestly report: whether the development-only mock method is available.
 * A future carrier adapter should add its own health description here
 * rather than this class guessing at an interface it does not yet know.
 */
final class ShippingHealth {

	public static function describe( bool $mock_method_allowed ): IntegrationStatus {
		if ( $mock_method_allowed ) {
			return IntegrationStatus::sandbox(
				'shipping',
				'Development mock shipping method active. Not usable in production.'
			);
		}
		return IntegrationStatus::disabled(
			'shipping',
			'No production-approved carrier is registered. Checkout fails closed on shipping eligibility.'
		);
	}
}
