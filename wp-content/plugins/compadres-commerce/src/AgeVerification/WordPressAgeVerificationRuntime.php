<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use Compadres\Commerce\Infrastructure\Environment;
use DateTimeImmutable;
use RuntimeException;

final class WordPressAgeVerificationRuntime {

	public const OPTION = 'compadres_age_verification';

	public function configuration(): ProviderConfiguration {
		$value    = get_option( self::OPTION, array() );
		$settings = array_merge( AgeVerificationSettings::defaults(), is_array( $value ) ? $value : array() );
		return ProviderConfiguration::fromArray( AgeVerificationSettings::sanitize( $settings ) );
	}

	public function environment(): Environment {
		$name = getenv( 'APP_ENV' );
		return Environment::fromString( false === $name ? 'production' : (string) $name );
	}

	public function provider(): AgeVerificationProvider {
		$transport = apply_filters( 'compadres_agechecker_transport', null );
		return ProviderFactory::create(
			$this->configuration(),
			$this->environment(),
			$transport instanceof AgeCheckerTransport ? $transport : null,
			static fn (): DateTimeImmutable => new DateTimeImmutable( 'now', wp_timezone() )
		);
	}

	public function store(): WooCommerceVerificationStore {
		$woocommerce = WC();
		if ( null === $woocommerce->session ) {
			throw new RuntimeException( 'WooCommerce checkout session is unavailable.' );
		}
		return new WooCommerceVerificationStore( new WooCommerceSessionAdapter( $woocommerce->session ) );
	}

	public function developmentBypassEnabled(): bool {
		return ! $this->configuration()->enabled() && $this->environment()->allowsDevelopmentProviders();
	}
}
