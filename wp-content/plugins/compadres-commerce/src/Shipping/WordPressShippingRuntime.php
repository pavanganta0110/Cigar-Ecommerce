<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

use Compadres\Commerce\Infrastructure\Environment;

/**
 * Resolves the active shipping provider for the current environment.
 *
 * Locally and in explicitly enabled staging, the deterministic mock provider
 * may be used. FedEx is selected only through explicit environment
 * configuration and remains fail closed until every required value is valid.
 */
final class WordPressShippingRuntime {

	private static ?ShippingMethodProvider $request_provider = null;

	private Environment $environment;
	private FedExConfiguration $fedex_configuration;
	private FedExTransport $fedex_transport;
	private string $provider_name;
	private ?ShippingMethodProvider $resolved_provider = null;
	private bool $use_request_provider;

	public function __construct(
		?Environment $environment = null,
		?FedExConfiguration $fedex_configuration = null,
		?FedExTransport $fedex_transport = null,
		?string $provider_name = null
	) {
		$this->use_request_provider = null === $environment
			&& null === $fedex_configuration
			&& null === $fedex_transport
			&& null === $provider_name;
		$this->environment          = $environment ?? Environment::fromString( (string) getenv( 'APP_ENV' ) );
		$this->fedex_configuration  = $fedex_configuration ?? FedExConfiguration::fromEnvironment( $this->environment );
		$this->fedex_transport      = $fedex_transport ?? new WordPressFedExTransport();
		$selected                   = strtolower( trim( $provider_name ?? (string) getenv( 'COMPADRES_SHIPPING_PROVIDER' ) ) );
		$this->provider_name        = '' !== $selected
			? $selected
			: ( $this->environment->allowsDevelopmentProviders() ? 'mock' : 'none' );
	}

	public function environment(): Environment {
		return $this->environment;
	}

	public function provider(): ShippingMethodProvider {
		if ( null !== $this->resolved_provider ) {
			return $this->resolved_provider;
		}
		if ( 'fedex' === $this->provider_name ) {
			if ( $this->use_request_provider && self::$request_provider instanceof FedExShippingProvider ) {
				$this->resolved_provider = self::$request_provider;
				return $this->resolved_provider;
			}
			$this->resolved_provider = new FedExShippingProvider(
				$this->fedex_configuration,
				new FedExApiClient( $this->fedex_configuration, $this->fedex_transport )
			);
			if ( $this->use_request_provider ) {
				self::$request_provider = $this->resolved_provider;
			}
			return $this->resolved_provider;
		}
		if ( $this->mockMethodAllowed() ) {
			$this->resolved_provider = new MockShippingProvider( ShippingSettings::scenario() );
			return $this->resolved_provider;
		}
		$this->resolved_provider = new NoShippingProvider();
		return $this->resolved_provider;
	}

	public function fedExMethodAllowed(): bool {
		return 'fedex' === $this->provider_name && $this->fedex_configuration->isConfigured();
	}

	/**
	 * Whether the development mock shipping method may be offered at all.
	 *
	 * Local/development: always. Staging: only when explicitly enabled via the
	 * COMPADRES_ENABLE_MOCK_SHIPPING environment variable. Production: never.
	 */
	public function mockMethodAllowed(): bool {
		if ( 'mock' !== $this->provider_name ) {
			return false;
		}
		if ( $this->environment->allowsDevelopmentProviders() ) {
			return true;
		}
		if ( 'staging' === $this->environment->value() ) {
			return '1' === (string) getenv( 'COMPADRES_ENABLE_MOCK_SHIPPING' );
		}
		return false;
	}
}
