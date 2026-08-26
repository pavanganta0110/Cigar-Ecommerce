<?php

declare(strict_types=1);

namespace Compadres\Commerce\Admin;

use Compadres\Commerce\Integrations\IntegrationStatus;
use Compadres\Commerce\Payments\GlobalPaymentsConfiguration;

/** Describes Global Payments (Unified Payments) integration health for the operations dashboard. */
final class PaymentHealth {

	public static function describe( GlobalPaymentsConfiguration $configuration ): IntegrationStatus {
		if ( ! $configuration->hasMerchantContactUrl() ) {
			return IntegrationStatus::disabled(
				'payments',
				'Global Payments merchant contact URL is not configured. Checkout fails closed on payment.'
			);
		}

		if ( $configuration->environment()->isProduction() ) {
			if ( $configuration->productionApproved() && $configuration->hasLiveCredentials() ) {
				return IntegrationStatus::connected(
					'payments',
					'Global Payments Unified Payments live mode active.',
					true
				);
			}
			return IntegrationStatus::disabled(
				'payments',
				'No production-approved Global Payments credentials configured. Checkout fails closed on payment.'
			);
		}

		if ( $configuration->hasSandboxCredentials() ) {
			return IntegrationStatus::sandbox(
				'payments',
				'Global Payments Unified Payments sandbox mode active. Not usable in production.'
			);
		}

		return IntegrationStatus::disabled( 'payments', 'No Global Payments sandbox credentials configured.' );
	}
}
