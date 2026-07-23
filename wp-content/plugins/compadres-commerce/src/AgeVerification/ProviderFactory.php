<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use Compadres\Commerce\Infrastructure\Environment;

final class ProviderFactory {

	public static function create( ProviderConfiguration $configuration, Environment $environment, ?AgeCheckerTransport $transport, callable $now ): AgeVerificationProvider {
		if ( ! $configuration->enabled() || '' === $configuration->provider() ) {
			return new UnavailableProvider( 'not_configured', $configuration->requiresDateOfBirth(), $now );
		}
		if ( 'mock' === $configuration->provider() ) {
			return $environment->allowsDevelopmentProviders()
				? new MockAgeVerificationProvider(
					$configuration->requiresDateOfBirth(),
					$now,
					$configuration->mockStatus(),
					$configuration->mockRefreshStatus(),
					$configuration->hostedUrlTemplate()
				)
				: new UnavailableProvider( 'mock_blocked_in_production', $configuration->requiresDateOfBirth(), $now );
		}
		if ( $environment->isProduction() && ! $configuration->productionApproved() ) {
			return new UnavailableProvider( 'provider_not_production_approved', $configuration->requiresDateOfBirth(), $now );
		}
		if ( null === $transport || '' === $configuration->hostedUrlTemplate() ) {
			return new UnavailableProvider( 'not_configured', $configuration->requiresDateOfBirth(), $now );
		}
		return new AgeCheckerProvider( $transport, $configuration->hostedUrlTemplate(), $now, $configuration->requiresDateOfBirth() );
	}
}
