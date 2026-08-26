<?php

declare(strict_types=1);

namespace Compadres\Commerce\Admin;

use Compadres\Commerce\AgeVerification\ProviderConfiguration;
use Compadres\Commerce\Integrations\IntegrationStatus;

/**
 * Describes age-verification integration health for the operations
 * dashboard.
 *
 * Deliberately independent of ProviderConfiguration::integrationStatus(),
 * which is display logic for the existing settings page and does not
 * currently distinguish a production-approved provider from one that is
 * merely configured; this dashboard needs that distinction to be correct.
 */
final class AgeVerificationHealth {

	public static function describe( ProviderConfiguration $configuration ): IntegrationStatus {
		if ( ! $configuration->enabled() || '' === $configuration->provider() ) {
			return IntegrationStatus::disabled(
				'age_verification',
				'No provider configured. Checkout blocks unless a development bypass is active.'
			);
		}
		if ( 'mock' === $configuration->provider() ) {
			return IntegrationStatus::sandbox(
				'age_verification',
				'Development mock provider active. Not usable in production.'
			);
		}
		return IntegrationStatus::connected(
			'age_verification',
			$configuration->productionApproved()
				? 'AgeChecker configured and approved for production.'
				: 'AgeChecker configured. Production approval has not been recorded.',
			$configuration->productionApproved()
		);
	}
}
