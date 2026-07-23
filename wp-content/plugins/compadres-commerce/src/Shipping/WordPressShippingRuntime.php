<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

use Compadres\Commerce\Infrastructure\Environment;

/**
 * Resolves the active shipping provider for the current environment.
 *
 * Locally and in explicitly enabled staging, the deterministic mock provider
 * is used. Production falls back to a non-configured provider (fail closed)
 * until an approved carrier adapter is registered.
 */
final class WordPressShippingRuntime {

	private Environment $environment;

	public function __construct( ?Environment $environment = null ) {
		$this->environment = $environment ?? Environment::fromString( (string) getenv( 'APP_ENV' ) );
	}

	public function environment(): Environment {
		return $this->environment;
	}

	public function provider(): ShippingMethodProvider {
		if ( $this->mockMethodAllowed() ) {
			return new MockShippingProvider( ShippingSettings::scenario() );
		}
		// No approved carrier adapter is registered yet. Fail closed.
		return new NoShippingProvider();
	}

	/**
	 * Whether the development mock shipping method may be offered at all.
	 *
	 * Local/development: always. Staging: only when explicitly enabled via the
	 * COMPADRES_ENABLE_MOCK_SHIPPING environment variable. Production: never.
	 */
	public function mockMethodAllowed(): bool {
		if ( $this->environment->allowsDevelopmentProviders() ) {
			return true;
		}
		if ( 'staging' === $this->environment->value() ) {
			return '1' === (string) getenv( 'COMPADRES_ENABLE_MOCK_SHIPPING' );
		}
		return false;
	}
}
